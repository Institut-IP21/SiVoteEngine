<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Set the application locale for API requests from the calling service (e.g.
 * web_app) so server-rendered strings — most notably the ballot component
 * tree's type names and descriptions — come back in the organizer's language
 * instead of the engine's configured default.
 */
class SetLocale
{
    private const array SUPPORTED = ['en', 'sl'];

    public function handle(Request $request, Closure $next)
    {
        $locale = substr((string) $request->header('Accept-Language'), 0, 2);

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
