<?php

namespace Tests\Feature\Security;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentLockGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $state
     * @return array{0: Election, 1: Ballot, 2: BallotComponent, 3: \Tests\TestCase}
     */
    private function ballotIn(array $state): array
    {
        $owner = (string) Str::uuid();
        $e = Election::factory()
            ->state(['owner' => $owner])
            ->has(Ballot::factory()->state($state))
            ->create();
        $b = $e->ballots[0];
        $c =BallotComponent::factory()->create([
            'ballot_id' => $b->id,
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => ['A', 'B', 'C'],
        ]);
        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner]);

        return [$e, $b, $c, $req];
    }

    public function test_update_component_rejected_on_active_ballot(): void
    {
        [$e, $b, $c, $req] = $this->ballotIn(['active' => true]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/$c->id", [
            'title' => 'Rewritten',
            'type' => 'YesNo',
            'version' => 'v1',
            'options' => ['X', 'Y', 'Z'],
        ])->assertStatus(409);

        $this->assertSame(['A', 'B', 'C'], $c->fresh()->options);
    }

    public function test_update_component_rejected_on_finished_ballot(): void
    {
        [$e, $b, $c, $req] = $this->ballotIn(['active' => false, 'finished' => true]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/$c->id", [
            'title' => 'Rewritten',
            'type' => 'YesNo',
            'version' => 'v1',
            'options' => ['X', 'Y', 'Z'],
        ])->assertStatus(409);

        $this->assertSame(['A', 'B', 'C'], $c->fresh()->options);
    }

    public function test_create_component_rejected_on_active_ballot(): void
    {
        [$e, $b, , $req] = $this->ballotIn(['active' => true]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'A new question',
            'type' => 'YesNo',
            'version' => 'v1',
        ])->assertStatus(409);
    }

    public function test_delete_component_rejected_on_active_and_finished_ballot(): void
    {
        [$e, $b, $c, $req] = $this->ballotIn(['active' => true]);
        $req->deleteJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertStatus(409);
        $this->assertNotNull($c->fresh(), 'Component must still exist.');

        [$e2, $b2, $c2, $req2] = $this->ballotIn(['active' => false, 'finished' => true]);
        $req2->deleteJson("/api/election/$e2->id/ballot/$b2->id/component/$c2->id")->assertStatus(409);
        $this->assertNotNull($c2->fresh(), 'Component must still exist.');
    }

    public function test_component_writes_allowed_on_draft_ballot(): void
    {
        [$e, $b, $c, $req] = $this->ballotIn(['active' => false, 'finished' => false]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/$c->id", [
            'title' => 'Edited draft',
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => ['X', 'Y', 'Z'],
        ])->assertStatus(200);
        $this->assertSame(['X', 'Y', 'Z'], $c->fresh()->options);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'A second question',
            'type' => 'YesNo',
            'version' => 'v1',
        ])->assertSuccessful();

        $req->deleteJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertStatus(200);
        $this->assertSoftDeleted('ballot_components', ['id' => $c->id]);
    }
}
