<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the interface language: explicit session choice, then the
 * account's preference, then the application default (Arabic).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('examis.supported_locales');

        $locale = $request->hasSession() ? $request->session()->get('locale') : null;
        $locale ??= $request->user()?->locale;

        if (is_string($locale) && in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
