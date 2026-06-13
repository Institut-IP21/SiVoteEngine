<?php

namespace Tests\Feature\Ballot;

use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D11: quorum over the HTTP API — update() persistence, validation, and the
 * additive resource fields.
 */
class BallotQuorumApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_persists_quorum(): void
    {
        $el = Election::factory()->hasBallots(1, ['active' => false])->create();
        $ballot = $el->ballots[0];

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->postJson("/api/election/$el->id/ballot/$ballot->id/update", [
            'quorum' => 12,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.quorum', 12);

        $this->assertSame(12, $ballot->fresh()->quorum);
    }

    public function test_update_rejects_zero_and_negative_quorum(): void
    {
        $el = Election::factory()->hasBallots(1, ['active' => false])->create();
        $ballot = $el->ballots[0];

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);

        $req->postJson("/api/election/$el->id/ballot/$ballot->id/update", ['quorum' => 0])
            ->assertStatus(400)
            ->assertJsonStructure(['field_errors' => ['quorum']]);

        $req->postJson("/api/election/$el->id/ballot/$ballot->id/update", ['quorum' => -3])
            ->assertStatus(400)
            ->assertJsonStructure(['field_errors' => ['quorum']]);
    }

    public function test_create_rejects_zero_quorum(): void
    {
        $el = Election::factory()->create();

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->postJson("/api/election/$el->id/ballot/create", [
            'title' => 'A perfectly valid title',
            'quorum' => 0,
        ])
            ->assertStatus(400)
            ->assertJsonStructure(['field_errors' => ['quorum']]);
    }

    public function test_resource_exposes_quorum_met_and_electorate_size(): void
    {
        $el = Election::factory()->hasBallots(1, ['active' => true])->create();
        $ballot = $el->ballots[0];

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->getJson("/api/election/$el->id/ballot/$ballot->id")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['quorum_met', 'electorate_size', 'votes_count'],
            ])
            ->assertJsonPath('data.quorum_met', true)        // null quorum → met
            ->assertJsonPath('data.electorate_size', 0);     // no codes issued
    }
}
