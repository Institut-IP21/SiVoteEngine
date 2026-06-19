<?php

declare(strict_types=1);

namespace Tests\Feature\BallotComponent;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Faker\Provider\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BallotComponentApiController::order — sets each component's `order` to its
 * index in a given id sequence (drag-to-reorder), with ownership, validation
 * and finished-ballot guards. Route: component.order.
 */
class BallotComponentReorderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An owned ballot with three ordered components.
     *
     * @return array{0: Election, 1: Ballot, 2: BallotComponent, 3: BallotComponent, 4: BallotComponent, 5: string}
     */
    private function ownedBallotWithThreeComponents(bool $finished = false): array
    {
        $owner = Uuid::uuid();
        $election = Election::factory()->state(['owner' => $owner])->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => $finished,
        ]);
        $a = BallotComponent::factory()->create(['ballot_id' => $ballot->id, 'order' => 0]);
        $b = BallotComponent::factory()->create(['ballot_id' => $ballot->id, 'order' => 1]);
        $c = BallotComponent::factory()->create(['ballot_id' => $ballot->id, 'order' => 2]);

        return [$election, $ballot, $a, $b, $c, $owner];
    }

    private function asOwner(string $owner): self
    {
        return $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner]);
    }

    public function test_order_sets_order_to_match_the_given_sequence(): void
    {
        [$e, $b, $a, $bb, $c, $owner] = $this->ownedBallotWithThreeComponents();

        // Reverse the order: c, b, a.
        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", [
            'order' => [$c->id, $bb->id, $a->id],
        ])->assertOk();

        $a->refresh();
        $bb->refresh();
        $c->refresh();

        $this->assertSame(0, $c->order);
        $this->assertSame(1, $bb->order);
        $this->assertSame(2, $a->order);
    }

    public function test_order_returns_the_ballot_resource_with_components_in_new_order(): void
    {
        [$e, $b, $a, $bb, $c, $owner] = $this->ownedBallotWithThreeComponents();

        $response = $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", [
            'order' => [$c->id, $a->id, $bb->id],
        ])->assertOk();

        // Same shape as switchOrder: a Ballot resource keyed by id under components.
        $response->assertJsonPath('data.id', $b->id);
        $response->assertJsonPath("data.components.{$c->id}.order", 0);
        $response->assertJsonPath("data.components.{$a->id}.order", 1);
        $response->assertJsonPath("data.components.{$bb->id}.order", 2);
    }

    public function test_order_ignores_ids_not_on_this_ballot(): void
    {
        [$e, $b, $a, $bb, $c, $owner] = $this->ownedBallotWithThreeComponents();

        // A component on a DIFFERENT ballot of the same owner.
        $otherBallot = Ballot::factory()->create(['election_id' => $e->id, 'finished' => false]);
        $foreign = BallotComponent::factory()->create(['ballot_id' => $otherBallot->id, 'order' => 7]);

        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", [
            'order' => [$foreign->id, $c->id, $a->id, $bb->id],
        ])->assertOk();

        // Foreign component is untouched...
        $foreign->refresh();
        $this->assertSame(7, $foreign->order);

        // ...and the foreign id did NOT consume index 0: our components are
        // packed from 0 in the order they appear among owned ids.
        $a->refresh();
        $bb->refresh();
        $c->refresh();
        $this->assertSame(0, $c->order);
        $this->assertSame(1, $a->order);
        $this->assertSame(2, $bb->order);
    }

    public function test_order_requires_an_array_of_uuids(): void
    {
        [$e, $b, , , , $owner] = $this->ownedBallotWithThreeComponents();

        // Missing order.
        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", [])
            ->assertStatus(400)->assertJsonStructure(['field_errors' => ['order']]);

        // Non-uuid entry.
        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", [
            'order' => ['not-a-uuid'],
        ])->assertStatus(400)->assertJsonStructure(['field_errors' => ['order.0']]);
    }

    public function test_order_rejected_on_finished_ballot(): void
    {
        [$e, $b, $a, $bb, $c, $owner] = $this->ownedBallotWithThreeComponents(finished: true);

        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", [
            'order' => [$c->id, $bb->id, $a->id],
        ])->assertStatus(403);

        $a->refresh();
        $this->assertSame(0, $a->order);
    }

    public function test_order_owner_mismatch_is_forbidden(): void
    {
        [$e, $b, $a, $bb, $c, ] = $this->ownedBallotWithThreeComponents();

        // A different owner cannot reorder another owner's ballot -> 403 (policy).
        $this->asOwner(Uuid::uuid())->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", [
            'order' => [$c->id, $bb->id, $a->id],
        ])->assertForbidden();

        // Order is untouched.
        $a->refresh();
        $this->assertSame(0, $a->order);
    }

    public function test_order_auth_battery(): void
    {
        [$e, $b, $a, $bb, $c, ] = $this->ownedBallotWithThreeComponents();
        $payload = ['order' => [$c->id, $bb->id, $a->id]];

        // No credentials at all -> 401 from ApiAuth.
        $this->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", $payload)
            ->assertUnauthorized();

        // Token but no Owner header -> 403 from ApiAuth.
        $this->withHeaders(['Authorization' => '123123123'])
            ->postJson("/api/election/{$e->id}/ballot/{$b->id}/component/order", $payload)
            ->assertForbidden();

        $a->refresh();
        $this->assertSame(0, $a->order);
    }
}
