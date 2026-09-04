<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Applies the user's language from the session (set by the locale switcher)
 * or the 'locale' cookie.
 *
 * Symfony 8's LocaleListener only reads the "_locale" request attribute, so
 * this listener must run before it (priority 16) and set that attribute.
 */
#[AsEventListener(event: 'kernel.request', priority: 20)]
final class LocaleCookieListener
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $locale = $request->hasSession() ? $request->getSession()->get('_locale') : null;
        if (!is_string($locale)) {
            $locale = $request->cookies->get('locale');
        }

        if (!is_string($locale) || !in_array($locale, ['en', 'ru'], true)) {
            return;
        }

        $request->attributes->set('_locale', $locale);
        $request->setLocale($locale);
    }
}
