<?php

declare(strict_types=1);

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * OAuth entry points. The check routes are handled by the security firewall
 * (OAuthAuthenticator), not by this controller.
 */
final class OAuthController extends AbstractController
{
    public function start(string $provider, ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient($provider)->redirect();
    }

    /** Never dispatched — the authenticator intercepts these paths. */
    public function check(): Response
    {
        return $this->redirectToRoute('app_login');
    }
}
