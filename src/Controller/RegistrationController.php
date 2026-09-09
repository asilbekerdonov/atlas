<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\RegisterRequestDTO;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\ExpiredSignedUriException;
use Symfony\Component\HttpFoundation\Exception\SignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Minimal email registration with a signed, expiring verification link.
 *
 * The link is produced by Symfony's UriSigner (HMAC over the absolute URL
 * plus an _expiration query parameter), so no random token needs to be
 * stored on the user row. isBlocked doubles as the "not yet verified" flag:
 * UserChecker refuses logins for blocked accounts until verify() unblocks.
 */
final class RegistrationController extends AbstractController
{
    /** Link lifetime, matching the verify-email-bundle default of 1 hour. */
    private const int VERIFY_TTL_SECONDS = 3600;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly MailerInterface $mailer,
        private readonly UriSigner $uriSigner,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        $dto = new RegisterRequestDTO(
            email: (string) $request->request->get('email', ''),
            password: (string) $request->request->get('password', ''),
            confirmPassword: (string) $request->request->get('confirmPassword', ''),
        );

        $errors = [];

        if ($request->isMethod('POST')) {
            $violations = $this->validator->validate($dto);
            foreach ($violations as $violation) {
                $errors[] = $violation->getMessage();
            }

            if ($dto->password !== $dto->confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }

            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $dto->email]);
            if ($existing !== null) {
                $errors[] = 'An account with this email already exists.';
            }

            if ($errors === []) {
                $user = new User($dto->email, [UserRole::ROLE_CANDIDATE->value]);
                $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
                $user->block(); // login refused until the email is verified
                $this->em->persist($user);
                $this->em->flush();

                $this->sendVerificationEmail($user);

                $this->addFlash('success', 'Check your email to confirm your account.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('registration/register.html.twig', [
            'email' => $dto->email,
            'errors' => $errors,
        ]);
    }

    #[Route('/register/verify/{id}', name: 'app_register_verify', requirements: ['id' => '\d+'])]
    public function verify(User $user, Request $request): Response
    {
        // Throws ExpiredSignedUriException / SignedUriException when the link
        // is stale, was tampered with, or got replayed after being consumed.
        try {
            $this->uriSigner->verify($request);
        } catch (ExpiredSignedUriException $e) {
            return $this->render('registration/verify_error.html.twig', [
                'reason' => 'expired',
                'email' => $user->getEmail(),
            ], new Response(null, Response::HTTP_GONE));
        } catch (SignedUriException $e) {
            return $this->render('registration/verify_error.html.twig', [
                'reason' => 'invalid',
                'email' => $user->getEmail(),
            ], new Response(null, Response::HTTP_BAD_REQUEST));
        }

        // Already verified → friendly notice instead of a confusing error.
        if (!$user->isBlocked()) {
            $this->addFlash('success', 'Your email was already confirmed.');

            return $this->redirectToRoute('app_login');
        }

        $user->unblock();
        $this->em->flush();

        $this->addFlash('success', 'Email confirmed. You can now log in.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/register/resend', name: 'app_register_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $email = (string) $request->request->get('email', '');
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user !== null && $user->isBlocked()) {
            $this->sendVerificationEmail($user);
            $this->addFlash('success', 'A new confirmation link has been sent.');
        } else {
            $this->addFlash('error', 'No pending account found for this email.');
        }

        return $this->redirectToRoute('app_login');
    }

    private function sendVerificationEmail(User $user): void
    {
        $route = $this->generateUrl(
            'app_register_verify',
            ['id' => $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        // HMAC-signed URL carrying its own expiration — no stored token.
        $url = $this->uriSigner->sign($route, time() + self::VERIFY_TTL_SECONDS);

        $email = (new Email())
            ->from($this->getParameter('mailer_from'))
            ->to($user->getEmail())
            ->subject('Confirm your email — ATLAS')
            ->html($this->renderView('emails/verify.html.twig', ['url' => $url]))
            ->text('Confirm your ATLAS account: ' . $url);

        $this->mailer->send($email);
    }
}
