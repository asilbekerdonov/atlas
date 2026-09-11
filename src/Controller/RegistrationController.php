<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\RegisterRequestDTO;
use App\Entity\User;
use App\Exception\AccountNotPendingException;
use App\Exception\RegistrationValidationException;
use App\Service\Auth\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\ExpiredSignedUriException;
use Symfony\Component\HttpFoundation\Exception\SignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
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
            try {
                $this->registrationService->register($dto);
                $this->addFlash('success', 'Check your email to confirm your account.');

                return $this->redirectToRoute('app_login');
            } catch (RegistrationValidationException $e) {
                $errors = $e->getErrors();
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
        } catch (ExpiredSignedUriException) {
            return $this->render('registration/verify_error.html.twig', [
                'reason' => 'expired',
                'email' => $user->getEmail(),
            ], new Response(null, Response::HTTP_GONE));
        } catch (SignedUriException) {
            return $this->render('registration/verify_error.html.twig', [
                'reason' => 'invalid',
                'email' => $user->getEmail(),
            ], new Response(null, Response::HTTP_BAD_REQUEST));
        }

        // Already verified → friendly notice instead of a confusing error.
        if (!$this->registrationService->verifyEmail($user)) {
            $this->addFlash('success', 'Your email was already confirmed.');

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('success', 'Email confirmed. You can now log in.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/register/resend', name: 'app_register_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $email = (string) $request->request->get('email', '');

        try {
            $this->registrationService->resendVerificationEmail($email);
            $this->addFlash('success', 'A new confirmation link has been sent.');
        } catch (AccountNotPendingException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_login');
    }
}
