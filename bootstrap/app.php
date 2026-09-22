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

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by(((string) $request->header('Authorization', '')) . '|' . ($request->ip() ?? 'unknown')));

        // Explicit bindings so ScopeRouteBindings sees hydrated Election/Ballot/Component instances.
        Route::bind('election', fn ($value) => Election::findOrFail($value));
        Route::bind('ballot', fn ($value) => Ballot::findOrFail($value));
        Route::bind('component', fn ($value) => BallotComponent::findOrFail($value));
    })
    ->create();
