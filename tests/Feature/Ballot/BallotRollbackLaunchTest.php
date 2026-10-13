<?php

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** POST election/{e}/ballot/{b}/rollback-launch: undo a failed launch (activate + generate codes). */
class BallotRollbackLaunchTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = (string) Str::uuid();
        config(['app.api.authlist' => ['tenant-token']]);
    }

    /** @param array<string, mixed> $state */
    private function ballot(array $state = []): Ballot
    {
        $election = Election::factory()->create(['owner' => $this->owner]);

        return Ballot::factory()->create(array_merge(['election_id' => $election->id], $state));
    }

    private function rollback(Ballot $ballot, ?string $owner = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Authorization' => 'tenant-token', 'Owner' => $owner ?? $this->owner])
            ->postJson("/api/election/$ballot->election_id/ballot/$ballot->id/rollback-launch");
    }

    public function test_rollback_returns_an_active_ballot_to_draft_and_deletes_codes(): void
    {
        $ballot = $this->ballot(['active' => true, 'opened_at' => Carbon::now()]);
        BallotComponent::factory()->create(['ballot_id' => $ballot->id]);
        Vote::factory()->count(3)->create(['ballot_id' => $ballot->id]);

        $this->rollback($ballot)
            ->assertOk()
            ->assertJsonPath('data.id', $ballot->id)
            ->assertJsonPath('data.active', false);

        $ballot->refresh();
        $this->assertFalse($ballot->active);
        $this->assertFalse((bool) $ballot->finished);
        $this->assertNull($ballot->opened_at);
        $this->assertNull($ballot->closed_at);
        $this->assertFalse($ballot->locked);
        $this->assertSame(0, Vote::withTrashed()->where('ballot_id', $ballot->id)->count());
        // Questions are untouched: the organiser keeps the built ballot.
        $this->assertSame(1, $ballot->components()->count());
    }

    public function test_rollback_refused_when_a_vote_was_cast(): void
    {
        $ballot = $this->ballot(['active' => true]);
        Vote::factory()->create(['ballot_id' => $ballot->id]);
        Vote::factory()->withValues(['x' => 'yes'])->create(['ballot_id' => $ballot->id]);

        $this->rollback($ballot)->assertStatus(409)->assertJsonStructure(['error']);

        $this->assertTrue($ballot->refresh()->active);
        $this->assertSame(2, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_rollback_refused_when_finished(): void
    {
        $ballot = $this->ballot(['active' => false, 'finished' => true]);
        Vote::factory()->create(['ballot_id' => $ballot->id]);

        $this->rollback($ballot)->assertStatus(409);
        $this->assertSame(1, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_rollback_refused_when_not_active(): void
    {
        $ballot = $this->ballot(['active' => false]);

        $this->rollback($ballot)->assertStatus(409);
    }

    public function test_rollback_wrong_owner_is_forbidden(): void
    {
        $ballot = $this->ballot(['active' => true]);
        Vote::factory()->create(['ballot_id' => $ballot->id]);

        $this->rollback($ballot, (string) Str::uuid())->assertForbidden();

        $this->assertTrue($ballot->refresh()->active);
        $this->assertSame(1, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_rollback_requires_auth(): void
    {
        $ballot = $this->ballot(['active' => true]);

        $this->postJson("/api/election/$ballot->election_id/ballot/$ballot->id/rollback-launch")->assertUnauthorized();
    }

    public function test_ballot_can_be_relaunched_after_rollback(): void
    {
        $ballot = $this->ballot(['active' => true]);
        Vote::factory()->count(2)->create(['ballot_id' => $ballot->id]);

        $this->rollback($ballot)->assertOk();

        $h = ['Authorization' => 'tenant-token', 'Owner' => $this->owner];
        $this->withHeaders($h)->postJson("/api/election/$ballot->election_id/ballot/$ballot->id/vote/generate", ['quantity' => 2])->assertOk();
        $this->withHeaders($h)->postJson("/api/election/$ballot->election_id/ballot/$ballot->id/activate")->assertOk();
    }
}
