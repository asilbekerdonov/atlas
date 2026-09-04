<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Language switcher: EN / RU. Stores the choice in a cookie and in the
 * session (_locale), then returns the visitor to the previous page.
 */
final class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_locale', requirements: ['locale' => 'en|ru'])]
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $request->getSession()->set('_locale', $locale);

        $response = $this->redirect($request->headers->get('referer', '/'));
        $response->headers->setCookie(new Cookie('locale', $locale, time() + 365 * 24 * 3600, '/', null, false, false));

        return $response;
    }
}
