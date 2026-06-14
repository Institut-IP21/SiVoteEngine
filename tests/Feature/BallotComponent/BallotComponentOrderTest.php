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
 * BallotApiController::switchOrder — swaps the `order` of two components,
 * with ownership, validation and finished-ballot guards.
 */
class BallotComponentOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An owned ballot with two ordered components. Does NOT set request headers
     * (withHeaders is sticky across a test), so callers add auth themselves.
     *
     * @return array{0: Election, 1: Ballot, 2: BallotComponent, 3: BallotComponent, 4: string}
     */
    private function ownedBallotWithTwoComponents(bool $finished = false): array
    {
        $owner = Uuid::uuid();
        $election = Election::factory()->state(['owner' => $owner])->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => $finished,
        ]);
        $first = BallotComponent::factory()->create(['ballot_id' => $ballot->id, 'order' => 0]);
        $second = BallotComponent::factory()->create(['ballot_id' => $ballot->id, 'order' => 1]);

        return [$election, $ballot, $first, $second, $owner];
    }

    /**
     * @return \Tests\TestCase
     */
    private function asOwner(string $owner): self
    {
        return $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner]);
    }

    public function test_switch_order_swaps_the_two_components(): void
    {
        [$e, $b, $first, $second, $owner] = $this->ownedBallotWithTwoComponents();

        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/switch-order", [
            'component1' => $first->id,
            'component2' => $second->id,
        ])->assertOk();

        $first->refresh();
        $second->refresh();
        $this->assertSame(1, $first->order);
        $this->assertSame(0, $second->order);
    }

    public function test_switch_order_requires_two_uuids(): void
    {
        [$e, $b, $first, $second, $owner] = $this->ownedBallotWithTwoComponents();

        // Missing component2.
        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/switch-order", [
            'component1' => $first->id,
        ])->assertStatus(400)->assertJsonStructure(['field_errors' => ['component2']]);

        // Non-uuid component1.
        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/switch-order", [
            'component1' => 'not-a-uuid',
            'component2' => $second->id,
        ])->assertStatus(400)->assertJsonStructure(['field_errors' => ['component1']]);
    }

    public function test_switch_order_rejected_on_finished_ballot(): void
    {
        [$e, $b, $first, $second, $owner] = $this->ownedBallotWithTwoComponents(finished: true);

        $this->asOwner($owner)->postJson("/api/election/{$e->id}/ballot/{$b->id}/switch-order", [
            'component1' => $first->id,
            'component2' => $second->id,
        ])->assertStatus(403);

        // Order is untouched.
        $first->refresh();
        $this->assertSame(0, $first->order);
    }

    public function test_switch_order_auth_battery(): void
    {
        // Guest assertions FIRST — withHeaders() is sticky once called.
        [$e, $b, $first, $second, $owner] = $this->ownedBallotWithTwoComponents();
        $payload = ['component1' => $first->id, 'component2' => $second->id];

        // No credentials at all -> 401 from ApiAuth.
        $this->postJson("/api/election/{$e->id}/ballot/{$b->id}/switch-order", $payload)
            ->assertUnauthorized();

        // Authenticated token but no Owner header -> 403 from ApiAuth.
        $this->withHeaders(['Authorization' => '123123123'])
            ->postJson("/api/election/{$e->id}/ballot/{$b->id}/switch-order", $payload)
            ->assertForbidden();

        // Order is untouched by any rejected request.
        $first->refresh();
        $this->assertSame(0, $first->order);
    }
}
