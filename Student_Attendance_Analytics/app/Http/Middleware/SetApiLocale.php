<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API messages in English or Khmer. The app sends "Accept-Language: km" or "en";
 * without it, the teacher's saved language is used.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = substr((string) $request->header('Accept-Language'), 0, 2);

        $locale = match (true) {
            in_array($header, SetLocale::LOCALES, true) => $header,
            in_array($request->user('sanctum')?->locale, SetLocale::LOCALES, true) => $request->user('sanctum')->locale,
            default => config('app.locale'),
        };

        app()->setLocale($locale);

        return $next($request);
    }
}
