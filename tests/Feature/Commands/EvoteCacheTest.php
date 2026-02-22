<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;

class EvoteCacheTest extends TestCase
{
    public function test_runs_successfully(): void
    {
        $this->artisan('evote:cache')
            ->assertExitCode(0);
    }
}
