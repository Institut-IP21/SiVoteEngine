<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EvoteCacheTest extends TestCase
{
    public function test_runs_successfully(): void
    {
        $this->artisan('evote:cache')
            ->assertExitCode(0);
    }

    /**
     * evote:cache writes config/route/view caches — and under the test suite those are
     * built from the TESTING env (:memory: sqlite, array session). If left behind,
     * bootstrap/cache/config.php is served by FPM and poisons the dev app (dashboard
     * 500s, Livewire 419s). Always clear the caches this test created.
     */
    protected function tearDown(): void
    {
        Artisan::call('optimize:clear');

        parent::tearDown();
    }
}
