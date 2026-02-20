<?php

declare(strict_types=1);

namespace App\Providers;

use App\BallotComponents\Support\ComponentRegistry;
use App\Services\BallotService;
use Illuminate\Support\ServiceProvider;

class BallotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ComponentRegistry::class, function ($app) {
            return new ComponentRegistry($app);
        });

        $this->app->singleton(BallotService::class, function ($app) {
            return new BallotService(
                $app->make(ComponentRegistry::class)
            );
        });

        // Backward compatibility alias
        $this->app->alias(BallotService::class, 'ballot');
    }
}
