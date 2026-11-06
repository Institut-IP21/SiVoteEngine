<?php

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.api' => \App\Http\Middleware\ApiAuth::class,
            'auth.api.admin' => \App\Http\Middleware\EnsureApiAdmin::class,
            // Enforce parent/child ownership on nested route models (election >
            // ballot > component) so an owner authorized on one election cannot
            // reach another election's ballot/component (cross-tenant IDOR).
            'scope.bindings' => \App\Http\Middleware\ScopeRouteBindings::class,
            // Lets the configured web_app origin iframe a response (preview only).
            'frame.webapp' => \App\Http\Middleware\AllowWebAppFraming::class,
        ]);

        // Throttle before ApiAuth so token guessing is limited too.
        $middleware->api(prepend: [
            'throttle:api',
            \App\Http\Middleware\ApiAuth::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Not '*': that lets any client forge X-Forwarded-For past the per-IP `votes` limiter.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/home');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->booted(function () {
        RateLimiter::for('votes', fn (Request $request) => Limit::perMinute(60)->by($request->ip() ?? 'unknown'));

        // The throttle runs before ApiAuth, so it checks the token itself:
        // - failed logins (401) are counted per client network (IPv4 address, IPv6
        //   /64) at 120/min; once that bucket is full EVERY request from there gets
        //   429, a correct token included, so guessing can't outrun the limit;
        // - a known token gets 300/min per organisation, so one busy organisation
        //   can't exhaust a budget the others share (web_app calls with one token
        //   from one IP), plus an overall ceiling per token as a safety net.
        RateLimiter::for('api', function (Request $request) {
            $auth = (string) $request->header('Authorization', '');
            $ip = (string) ($request->ip() ?? 'unknown');
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $ip = substr(bin2hex((string) inet_pton($ip)), 0, 16) . '::/64';
            }

            $authFailures = Limit::perMinute(120)
                ->by('api-authfail|' . $ip)
                ->after(fn ($response): bool => $response->getStatusCode() === 401);

            if (! \App\Http\Middleware\ApiAuth::isKnownToken($auth)) {
                return $authFailures;
            }

            $token = hash('sha256', $auth);
            $owner = (string) $request->header('Owner', '');
            // ApiAuth refuses a non-uuid Owner anyway; don't mint a bucket per junk value.
            $owner = \Ramsey\Uuid\Uuid::isValid($owner) ? strtolower($owner) : 'invalid';

            return [
                $authFailures,
                Limit::perMinute(300)->by('api-owner|' . $token . '|' . $owner),
                Limit::perMinute(3000)->by('api-token|' . $token),
            ];
        });

        // Explicit bindings so ScopeRouteBindings sees hydrated Election/Ballot/Component instances.
        Route::bind('election', fn ($value) => Election::findOrFail($value));
        Route::bind('ballot', fn ($value) => Ballot::findOrFail($value));
        Route::bind('component', fn ($value) => BallotComponent::findOrFail($value));
    })
    ->create();
