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
            // Enforce parent/child ownership on nested route models (election >
            // ballot > component) so an owner authorized on one election cannot
            // reach another election's ballot/component (cross-tenant IDOR).
            'scope.bindings' => \App\Http\Middleware\ScopeRouteBindings::class,
            // Lets the configured web_app origin iframe a response (preview only).
            'frame.webapp' => \App\Http\Middleware\AllowWebAppFraming::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ApiAuth::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Behind a reverse proxy (nginx); trust forwarded headers so the client
        // IP used by the `votes` rate limiter is the real voter, not the proxy.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/home');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->booted(function () {
        // Rate-limit public vote submissions per client IP (replaces the
        // RouteServiceProvider boot in the pre-slim skeleton).
        RateLimiter::for('votes', fn (Request $request) => Limit::perMinute(60)->by($request->ip() ?? 'unknown'));

        // Explicit bindings so nested model resolution + ScopeRouteBindings see
        // hydrated Election/Ballot/Component instances (he only bound component).
        Route::bind('election', fn ($value) => Election::findOrFail($value));
        Route::bind('ballot', fn ($value) => Ballot::findOrFail($value));
        Route::bind('component', fn ($value) => BallotComponent::findOrFail($value));
    })
    ->create();
