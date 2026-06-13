<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * The app is only reached through the reverse proxy / load balancer in front
     * of it, so trust forwarded headers to recover the real client IP. Without
     * this, X-Forwarded-For is ignored and $request->ip() returns the proxy's IP
     * for every request — collapsing the per-IP vote rate limiter onto a single
     * bucket and blocking legitimate voters during an election.
     *
     * NOTE: the edge proxy (nginx/LB) must set and sanitise X-Forwarded-For so
     * clients cannot spoof it; tighten '*' to the known proxy subnet if the app
     * is ever reachable without passing through the proxy.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
