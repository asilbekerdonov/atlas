<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\DTO\Request\RegisterRequestDTO;
use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\AccountNotPendingException;
use App\Exception\RegistrationValidationException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;

/**
 * Handles user registration, email verification links, and resending logic.
 */
final class RegistrationService
{
    /** Link lifetime, matching the verify-email-bundle default of 1 hour. */
    private const int VERIFY_TTL_SECONDS = 3600;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly MailerInterface $mailer,
        private readonly UriSigner $uriSigner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        #[Autowire('%mailer_from%')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Registers a new pending user with candidate role and sends verification email.
     *
     * @throws RegistrationValidationException
     */
    public function register(RegisterRequestDTO $dto): User
    {
        $errors = [];

        $violations = $this->validator->validate($dto);
        foreach ($violations as $violation) {
            $errors[] = $violation->getMessage();
        }

        if ($dto->password !== $dto->confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        $existing = $this->userRepository->findOneByEmail($dto->email);
        if ($existing !== null) {
            $errors[] = 'An account with this email already exists.';
        }

        if ($errors !== []) {
            throw new RegistrationValidationException($errors);
        }

        $user = new User($dto->email, [UserRole::ROLE_CANDIDATE->value]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));
        $user->block(); // login refused until the email is verified
        $this->em->persist($user);
        $this->em->flush();

        $this->sendVerificationEmail($user);

        return $user;
    }

    /**
     * Unblocks the user account if pending.
     *
     * @return bool True if freshly verified, false if already confirmed.
     */
    public function verifyEmail(User $user): bool
    {
        if (!$user->isBlocked()) {
            return false;
        }

        $user->unblock();
        $this->em->flush();

        return true;
    }

    /**
     * Resends verification email for a pending account.
     *
     * @throws AccountNotPendingException
     */
    public function resendVerificationEmail(string $email): void
    {
        $user = $this->userRepository->findOneByEmail($email);

        if ($user !== null && $user->isBlocked()) {
            $this->sendVerificationEmail($user);

            return;
        }

        throw new AccountNotPendingException('No pending account found for this email.');
    }

    public function sendVerificationEmail(User $user): void
    {
        $route = $this->urlGenerator->generate(
            'app_register_verify',
            ['id' => $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        // HMAC-signed URL carrying its own expiration — no stored token.
        $url = $this->uriSigner->sign($route, time() + self::VERIFY_TTL_SECONDS);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject('Confirm your email — ATLAS')
            ->html($this->twig->render('emails/verify.html.twig', ['url' => $url]))
            ->text('Confirm your ATLAS account: ' . $url);

        $this->mailer->send($email);
    }
}
