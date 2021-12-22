<?php

namespace App\Providers;

use App\Services\BallotService;
use Illuminate\Support\ServiceProvider;

class BallotServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->singleton('ballot', function () {
            return new BallotService();
        });
    }
}
