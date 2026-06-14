<?php

declare(strict_types=1);

namespace Tests\Feature\Voting;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BallotController::preview — renders a ballot WITHOUT requiring a voting code,
 * and must never be usable as a path to cast a vote.
 */
class BallotPreviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Election, 1: Ballot}
     */
    private function makeBallot(bool $active = false): array
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => $active,
            'mode' => Ballot::MODE_BASIC,
        ]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'YesNo',
            'version' => 'v1',
            'options' => ['yes', 'no'],
        ]);

        return [$election, $ballot];
    }

    public function test_preview_renders_without_a_code_for_a_draft_ballot(): void
    {
        [$election, $ballot] = $this->makeBallot(active: false);

        $this->get("/election/{$election->id}/ballot/{$ballot->id}/preview")
            ->assertStatus(200)
            ->assertViewIs('ballot-preview');
    }

    public function test_preview_renders_for_an_active_ballot_too(): void
    {
        [$election, $ballot] = $this->makeBallot(active: true);

        $this->get("/election/{$election->id}/ballot/{$ballot->id}/preview")
            ->assertStatus(200)
            ->assertViewIs('ballot-preview');
    }

    public function test_preview_does_not_create_or_cast_any_vote(): void
    {
        [$election, $ballot] = $this->makeBallot();

        $this->assertSame(0, Vote::count());

        $this->get("/election/{$election->id}/ballot/{$ballot->id}/preview")
            ->assertStatus(200);

        // Preview is read-only: it must not mint or cast a Vote.
        $this->assertSame(0, Vote::count());
    }

    public function test_preview_does_not_crash_without_personalization(): void
    {
        // No Personalization row exists for the owner; the view must tolerate null.
        [$election, $ballot] = $this->makeBallot();

        $this->get("/election/{$election->id}/ballot/{$ballot->id}/preview")
            ->assertStatus(200);
    }
}
