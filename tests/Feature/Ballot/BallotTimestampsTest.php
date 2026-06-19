<?php

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Custody timestamps for the web_app dashboard: a ballot stamps `opened_at`
 * when it first goes active and `closed_at` when it is deactivated/finished.
 * The API resource exposes both as ISO-8601 strings (or null).
 */
class BallotTimestampsTest extends TestCase
{
    use RefreshDatabase;

    public function test_activating_a_ballot_stamps_opened_at(): void
    {
        $el = Election::factory()->hasBallots(1, ['active' => false])->create();
        $ballot = $el->ballots[0];
        $this->assertNull($ballot->opened_at);

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->postJson("/api/election/$el->id/ballot/$ballot->id/activate")
            ->assertStatus(200)
            ->assertJsonPath('data.active', true);

        $ballot->refresh();
        $this->assertNotNull($ballot->opened_at);
        $this->assertNull($ballot->closed_at);
    }

    public function test_reactivating_does_not_move_opened_at(): void
    {
        $opened = Carbon::now()->subDays(5);
        $el = Election::factory()
            ->hasBallots(1, ['active' => true, 'opened_at' => $opened])
            ->create();
        $ballot = $el->ballots[0];

        // The model's guard must keep the original timestamp.
        $this->assertTrue($ballot->activate());
        $ballot->refresh();

        $this->assertSame($opened->toIso8601String(), $ballot->opened_at?->toIso8601String());
    }

    public function test_deactivating_a_ballot_stamps_closed_at(): void
    {
        $el = Election::factory()
            ->hasBallots(1, ['active' => true, 'opened_at' => Carbon::now()->subDays(2)])
            ->create();
        $ballot = $el->ballots[0];
        $this->assertNull($ballot->closed_at);

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->postJson("/api/election/$el->id/ballot/$ballot->id/deactivate")
            ->assertStatus(200)
            ->assertJsonPath('data.finished', true);

        $ballot->refresh();
        $this->assertNotNull($ballot->closed_at);
        $this->assertNotNull($ballot->opened_at);
    }

    public function test_resource_exposes_timestamps_as_iso_strings(): void
    {
        $opened = Carbon::now()->subDays(4);
        $closed = Carbon::now()->subDays(1);
        $el = Election::factory()
            ->hasBallots(1, [
                'finished' => true,
                'opened_at' => $opened,
                'closed_at' => $closed,
            ])
            ->create();
        $ballot = $el->ballots[0];

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->getJson("/api/election/$el->id/ballot/$ballot->id")
            ->assertStatus(200)
            ->assertJsonPath('data.opened_at', $opened->toIso8601String())
            ->assertJsonPath('data.closed_at', $closed->toIso8601String());
    }

    public function test_resource_emits_null_when_timestamps_unset(): void
    {
        $el = Election::factory()->hasBallots(1, ['active' => false])->create();
        $ballot = $el->ballots[0];

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->getJson("/api/election/$el->id/ballot/$ballot->id")
            ->assertStatus(200)
            ->assertJsonPath('data.opened_at', null)
            ->assertJsonPath('data.closed_at', null);
    }
}
