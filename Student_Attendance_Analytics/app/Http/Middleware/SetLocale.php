<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows every page in English or Khmer.
 * The choice is kept in the session; a logged-in user's saved language is used first.
 */
class SetLocale
{
    public const LOCALES = ['en', 'km'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->user()?->locale ?? config('app.locale'));

        if (in_array($locale, self::LOCALES, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
