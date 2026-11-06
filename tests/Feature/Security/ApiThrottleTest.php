<?php

namespace Tests\Feature\Security;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiThrottleTest extends TestCase
{
    use RefreshDatabase;

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

    private string $token = 'tenant-token-for-throttle-test';

    /** @return list<Limit> */
    private function limitsFor(string $auth, string $owner, string $ip = '10.0.0.1'): array
    {
        config(['app.api.authlist' => [$this->token], 'app.api.admin_authlist' => []]);
        $request = Request::create('/api/election', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
        $request->headers->set('Authorization', $auth);
        $request->headers->set('Owner', $owner);

        $limits = RateLimiter::limiter('api')($request);

        return array_values(is_array($limits) ? $limits : [$limits]);
    }

    public function test_unknown_token_is_limited_per_ip_whatever_the_owner_header(): void
    {
        $a = $this->limitsFor('guess-1', (string) Str::uuid());
        $b = $this->limitsFor('guess-2', (string) Str::uuid());
        $other = $this->limitsFor('guess-3', (string) Str::uuid(), '10.0.0.2');

        $this->assertCount(1, $a);
        $this->assertSame(120, $a[0]->maxAttempts);
        // Rotating the token or the Owner header doesn't open a fresh bucket.
        $this->assertSame($a[0]->key, $b[0]->key);
        $this->assertNotSame($a[0]->key, $other[0]->key);
    }

    public function test_ipv6_clients_share_a_bucket_per_64(): void
    {
        $a = $this->limitsFor('guess', (string) Str::uuid(), '2001:db8:1:2::1');
        $b = $this->limitsFor('guess', (string) Str::uuid(), '2001:db8:1:2:ffff::9');
        $c = $this->limitsFor('guess', (string) Str::uuid(), '2001:db8:1:3::1');

        $this->assertSame($a[0]->key, $b[0]->key);
        $this->assertNotSame($a[0]->key, $c[0]->key);
    }

    public function test_empty_authorization_and_empty_authlist_entry_are_unknown(): void
    {
        config(['app.api.authlist' => [''], 'app.api.admin_authlist' => []]);
        $this->assertFalse(\App\Http\Middleware\ApiAuth::isKnownToken(''));
        $this->assertCount(1, $this->limitsFor('', (string) Str::uuid()));
    }

    public function test_known_token_is_limited_per_organisation_with_a_token_ceiling(): void
    {
        $a = $this->limitsFor($this->token, (string) Str::uuid());
        $b = $this->limitsFor($this->token, (string) Str::uuid());

        $this->assertSame([120, 300, 3000], array_map(fn (Limit $l): int => $l->maxAttempts, $a));
        $this->assertSame($a[0]->key, $b[0]->key, 'the failed-login bucket still applies');
        $this->assertNotSame($a[1]->key, $b[1]->key, 'each organisation has its own budget');
        $this->assertSame($a[2]->key, $b[2]->key, 'the token ceiling is shared');
        $this->assertStringNotContainsString($this->token, $a[1]->key, 'raw token must not be a cache key');
    }

    public function test_junk_owner_values_share_one_bucket(): void
    {
        $a = $this->limitsFor($this->token, 'junk-1');
        $b = $this->limitsFor($this->token, '');

        $this->assertSame($a[1]->key, $b[1]->key);
    }

    public function test_a_locked_out_client_is_refused_even_with_the_correct_token(): void
    {
        config(['app.api.authlist' => [$this->token], 'app.api.admin_authlist' => []]);
        $owner = (string) Str::uuid();

        for ($i = 0; $i < 120; $i++) {
            $this->withHeaders(['Authorization' => 'guess-' . $i, 'Owner' => $owner])
                ->getJson('/api/election')->assertStatus(401);
        }
        $this->withHeaders(['Authorization' => 'guess-x', 'Owner' => $owner])
            ->getJson('/api/election')->assertStatus(429);
        // A correct guess must not get through either.
        $this->withHeaders(['Authorization' => $this->token, 'Owner' => $owner])
            ->getJson('/api/election')->assertStatus(429);
    }

    public function test_successful_requests_do_not_count_as_failed_logins(): void
    {
        config(['app.api.authlist' => [$this->token], 'app.api.admin_authlist' => []]);

        for ($i = 0; $i < 130; $i++) {
            $this->withHeaders(['Authorization' => $this->token, 'Owner' => (string) Str::uuid()])
                ->getJson('/api/election')->assertOk();
        }
        $this->withHeaders(['Authorization' => 'guess', 'Owner' => (string) Str::uuid()])
            ->getJson('/api/election')->assertStatus(401);
    }

    public function test_a_busy_organisation_does_not_block_another(): void
    {
        config(['app.api.authlist' => [$this->token], 'app.api.admin_authlist' => []]);
        $busy = (string) Str::uuid();
        $other = (string) Str::uuid();

        for ($i = 0; $i < 300; $i++) {
            $this->withHeaders(['Authorization' => $this->token, 'Owner' => $busy])
                ->getJson('/api/election')->assertOk();
        }

        $this->withHeaders(['Authorization' => $this->token, 'Owner' => $busy])
            ->getJson('/api/election')->assertStatus(429);
        $this->withHeaders(['Authorization' => $this->token, 'Owner' => $other])
            ->getJson('/api/election')->assertOk();
    }
}
