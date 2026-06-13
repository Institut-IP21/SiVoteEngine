<?php

namespace App\Providers;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    // The controller namespace for the application. When present, controller
    // route declarations are automatically prefixed with this namespace.
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        // Explicit binds so {election} and {ballot} always resolve to models even
        // on routes where they are not method-typed (e.g. component listing),
        // which the ScopeRouteBindings middleware relies on to scope nested access.
        Route::bind('election', function ($value) {
            return Election::findOrFail($value);
        });

        Route::bind('ballot', function ($value) {
            return Ballot::findOrFail($value);
        });

        Route::bind('component', function ($value) {
            return BallotComponent::findOrFail($value);
        });

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // Public voter-facing ballot submission. Codes are 122-bit random UUIDs so
        // guessing is already infeasible; this limiter is abuse/DoS protection. Kept
        // generous and keyed by IP — note voters behind a shared corporate NAT share
        // the bucket, so tune if large orgs vote from one egress IP.
        RateLimiter::for('votes', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
