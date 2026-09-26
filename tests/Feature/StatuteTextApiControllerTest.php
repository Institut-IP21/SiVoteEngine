<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/component-types/statute — the global, non-election-scoped bilingual
 * statute/legal-reference endpoint (statute-feature-spec.md §2.4). Behind the
 * shared-token ApiAuth only (no scope.bindings, no can:view,election) — same
 * auth pattern as `admin/stats` (AdminStatsTest).
 */
class StatuteTextApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api.authlist' => [$this->token]]);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return ['Authorization' => $this->token, 'Owner' => (string) Str::uuid()];
    }

    public function test_missing_or_invalid_authorization_is_401(): void
    {
        $this->getJson('/api/component-types/statute')
            ->assertStatus(401)
            ->assertJson(['error' => 'No authorization or invalid.']);

        $this->withHeaders(['Authorization' => 'wrong-token', 'Owner' => (string) Str::uuid()])
            ->getJson('/api/component-types/statute')
            ->assertStatus(401)
            ->assertJson(['error' => 'No authorization or invalid.']);
    }

    public function test_valid_token_without_owner_header_is_403(): void
    {
        $this->withHeaders(['Authorization' => $this->token])
            ->getJson('/api/component-types/statute')
            ->assertStatus(403)
            ->assertJson(['error' => 'No owner.']);
    }

    public function test_returns_all_five_types_with_both_locales_and_the_quorum_preamble(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/component-types/statute');

        $response->assertOk()->assertJsonStructure([
            'YesNo' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'FirstPastThePost' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'RankedChoice' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'ApprovalVote' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'OrderedList' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'quorum' => ['en', 'sl'],
        ]);

        $body = $response->json();
        foreach (['YesNo', 'FirstPastThePost', 'RankedChoice', 'ApprovalVote', 'OrderedList'] as $type) {
            $this->assertNotSame('', trim((string) $body[$type]['name']['en']));
            $this->assertNotSame('', trim((string) $body[$type]['name']['sl']));
            $this->assertNotSame('', trim((string) $body[$type]['method']['en']));
            $this->assertNotSame('', trim((string) $body[$type]['method']['sl']));
            $this->assertNotEmpty($body[$type]['statute']['en']);
            $this->assertNotEmpty($body[$type]['statute']['sl']);
        }

        $this->assertNotEmpty($body['quorum']['en']);
        $this->assertNotEmpty($body['quorum']['sl']);
    }

    public function test_names_and_methods_differ_between_locales(): void
    {
        // A basic sanity check that en/sl are genuinely distinct payloads, not
        // the same string duplicated under both keys.
        $body = $this->withHeaders($this->authHeaders())
            ->getJson('/api/component-types/statute')
            ->json();

        $this->assertNotSame($body['YesNo']['name']['en'], $body['YesNo']['name']['sl']);
        $this->assertNotSame($body['YesNo']['statute']['en'], $body['YesNo']['statute']['sl']);
    }
}
