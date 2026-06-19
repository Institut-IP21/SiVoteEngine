<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allow the configured web_app origin to embed this response in an <iframe>.
 *
 * The ballot builder in web_app embeds the engine's real preview page so the
 * organiser sees exactly what voters will. By default framing is denied
 * (X-Frame-Options: SAMEORIGIN, set globally / by nginx), which blocks the
 * cross-origin embed. For the preview route ONLY we drop X-Frame-Options
 * (the modern, frame-ancestors-aware equivalent has no single-origin syntax,
 * so the header must go) and pin framing to exactly two origins via CSP:
 * 'self' and the known web_app origin. Never "*".
 */
class AllowWebAppFraming
{
    /**
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // X-Frame-Options can't express an allow-list of origins, so remove it
        // and rely on CSP frame-ancestors below. (Browsers that honour both
        // would otherwise still block on the SAMEORIGIN value.)
        $response->headers->remove('X-Frame-Options');

        $webAppOrigin = (string) config('app.web_app_url');

        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self' " . $webAppOrigin
        );

        return $response;
    }
}
