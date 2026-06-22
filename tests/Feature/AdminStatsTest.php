<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/admin/stats — the operator panel's read-only, cross-owner aggregate
 * endpoint. Behind the shared-token ApiAuth (so a bad/absent token is 401 and a
 * missing Owner header is 403), but GLOBAL: the Owner header is required yet
 * deliberately ignored, so its value never changes the counts.
 */
class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-token';

    protected function setUp(): void
    {
        parent::setUp();
        // Pin the accepted shared token for this test (ApiAuth reads this list).
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
        $this->getJson('/api/admin/stats')
            ->assertStatus(401)
            ->assertJson(['error' => 'No authorization or invalid.']);

        $this->withHeaders(['Authorization' => 'wrong-token', 'Owner' => (string) Str::uuid()])
            ->getJson('/api/admin/stats')
            ->assertStatus(401)
            ->assertJson(['error' => 'No authorization or invalid.']);
    }

    public function test_valid_token_without_owner_header_is_403(): void
    {
        $this->withHeaders(['Authorization' => $this->token])
            ->getJson('/api/admin/stats')
            ->assertStatus(403)
            ->assertJson(['error' => 'No owner.']);
    }

    public function test_stats_shape_and_aggregates(): void
    {
        $now = Carbon::parse('2026-06-15 12:00:00');
        Carbon::setTestNow($now);

        // Two elections at different levels, created this month.
        $electionA = Election::factory()->create(['level' => 1, 'created_at' => $now]);
        $electionB = Election::factory()->create(['level' => 2, 'created_at' => $now]);

        // Ballots: 2 active, 1 finished, 1 neither (draft).
        Ballot::factory()->create(['election_id' => $electionA->id, 'active' => true, 'finished' => false]);
        Ballot::factory()->create(['election_id' => $electionA->id, 'active' => true, 'finished' => false]);
        $finished = Ballot::factory()->create(['election_id' => $electionB->id, 'active' => false, 'finished' => true]);
        Ballot::factory()->create(['election_id' => $electionB->id, 'active' => false, 'finished' => false]);

        // Components of a couple of stored (class-name) types.
        BallotComponent::factory()->create(['ballot_id' => $finished->id, 'type' => 'YesNo']);
        BallotComponent::factory()->create(['ballot_id' => $finished->id, 'type' => 'YesNo']);
        BallotComponent::factory()->create(['ballot_id' => $finished->id, 'type' => 'FirstPastThePost']);
        BallotComponent::factory()->create(['ballot_id' => $finished->id, 'type' => 'RankedChoice']);

        $response = $this->withHeaders($this->authHeaders())->getJson('/api/admin/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'stats' => [
                    'elections_total',
                    'elections_by_level',
                    'ballots_total',
                    'ballots_active',
                    'ballots_finished',
                    'questions_by_type',
                    'elections_by_month',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('stats.elections_total', 2)
            ->assertJsonPath('stats.elections_by_level.1', 1)
            ->assertJsonPath('stats.elections_by_level.2', 1)
            ->assertJsonPath('stats.elections_by_level.3', 0)
            ->assertJsonPath('stats.ballots_total', 4)
            ->assertJsonPath('stats.ballots_active', 2)
            ->assertJsonPath('stats.ballots_finished', 1)
            ->assertJsonPath('stats.questions_by_type.YesNo', 2)
            ->assertJsonPath('stats.questions_by_type.FirstPastThePost', 1)
            ->assertJsonPath('stats.questions_by_type.RankedChoice', 1)
            ->assertJsonPath('stats.questions_by_type.ApprovalVote', 0);

        // elections_by_month: 12-element ascending series of {month, count}.
        /** @var list<array{month: string, count: int}> $byMonth */
        $byMonth = $response->json('stats.elections_by_month');
        $this->assertCount(12, $byMonth);
        foreach ($byMonth as $bucket) {
            $this->assertArrayHasKey('month', $bucket);
            $this->assertArrayHasKey('count', $bucket);
        }
        // Both elections were created in the current month → its bucket counts 2.
        $current = collect($byMonth)->firstWhere('month', $now->format('Y-m'));
        $this->assertNotNull($current);
        $this->assertSame(2, $current['count']);

        Carbon::setTestNow();
    }

    public function test_counts_are_global_and_ignore_the_owner_header(): void
    {
        Election::factory()->count(3)->create();

        $ownerOne = $this->withHeaders(['Authorization' => $this->token, 'Owner' => (string) Str::uuid()])
            ->getJson('/api/admin/stats');
        $ownerTwo = $this->withHeaders(['Authorization' => $this->token, 'Owner' => (string) Str::uuid()])
            ->getJson('/api/admin/stats');

        $ownerOne->assertOk()->assertJsonPath('stats.elections_total', 3);
        $ownerTwo->assertOk()->assertJsonPath('stats.elections_total', 3);
        $this->assertSame(
            $ownerOne->json('stats'),
            $ownerTwo->json('stats'),
            'A different Owner header must not change the global aggregates.'
        );
    }
}
