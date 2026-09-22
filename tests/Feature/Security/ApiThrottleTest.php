<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiThrottleTest extends TestCase
{
    public function test_api_middleware_group_includes_the_throttle(): void
    {
        $group = app('router')->getMiddlewareGroups()['api'] ?? [];

        $this->assertContains(
            'throttle:api',
            $group,
            'The api middleware group must run through the api throttle.'
        );
    }

    public function test_api_routes_use_the_api_group(): void
    {
        $route = Route::getRoutes()->getByName('election.api.list');
        $this->assertNotNull($route);
        $this->assertContains('api', $route->gatherMiddleware());
    }

    public function test_admin_route_uses_the_api_group(): void
    {
        $route = Route::getRoutes()->getByName('admin.stats');
        $this->assertNotNull($route);
        $this->assertContains('api', $route->gatherMiddleware());
    }

    public function test_public_vote_route_keeps_a_tighter_limiter(): void
    {
        $route = Route::getRoutes()->getByName('ballot.vote');
        $this->assertNotNull($route);
        $this->assertContains('throttle:votes', $route->gatherMiddleware());
    }
}
