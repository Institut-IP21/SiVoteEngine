<?php

namespace Tests\Feature\Voting;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end validation of vote submission, focused on the integrity of the
 * array-type components (ApprovalVote, RankedChoice) whose submissions must be
 * arrays of whitelisted, distinct options. A crafted scalar value must never be
 * accepted, as it would bypass the option whitelist entirely.
 */
class VoteValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function make(array $componentConfigs, array $electionAttrs = [], array $ballotAttrs = []): array
    {
        $election = Election::factory()->create(array_merge(['abstainable' => false], $electionAttrs));
        $ballot = Ballot::factory()->create(array_merge([
            'election_id' => $election->id, 'active' => true, 'mode' => Ballot::MODE_BASIC,
        ], $ballotAttrs));
        $components = [];
        foreach ($componentConfigs as $c) {
            $components[] = BallotComponent::factory()->create(array_merge(['ballot_id' => $ballot->id], $c));
        }
        return [$election, $ballot, $components];
    }

    protected function submit(Election $election, Ballot $ballot, Vote $vote, array $selections)
    {
        return $this->post("/election/{$election->id}/ballot/{$ballot->id}", array_merge(['code' => $vote->id], $selections));
    }

    // ----------------------------------------------------------------
    // ApprovalVote
    // ----------------------------------------------------------------

    public function test_approval_scalar_value_is_rejected(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'A', 'options' => ['X', 'Y']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submit($election, $ballot, $vote, [$components[0]->id => 'TOTALLY_FAKE'])
            ->assertViewIs('vote-failed');

        $vote->refresh();
        $this->assertNull($vote->values);
    }

    public function test_approval_scalar_abstain_rejected_even_when_not_abstainable(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'A', 'options' => ['X', 'Y']],
        ], ['abstainable' => false]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submit($election, $ballot, $vote, [$components[0]->id => 'abstain'])
            ->assertViewIs('vote-failed');
    }

    public function test_approval_array_with_invalid_option_is_rejected(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'A', 'options' => ['X', 'Y']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submit($election, $ballot, $vote, [$components[0]->id => ['X', 'EVIL']])
            ->assertViewIs('vote-failed');
    }

    public function test_approval_duplicate_selection_is_rejected(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'A', 'options' => ['X', 'Y']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        // Approving the same option twice must not inflate its tally.
        $this->submit($election, $ballot, $vote, [$components[0]->id => ['X', 'X']])
            ->assertViewIs('vote-failed');
    }

    public function test_approval_valid_array_is_accepted_and_stored(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'A', 'options' => ['X', 'Y', 'Z']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submit($election, $ballot, $vote, [$components[0]->id => ['X', 'Z']])
            ->assertViewIs('voted');

        $vote->refresh();
        $this->assertEquals(['X', 'Z'], $vote->values[$components[0]->id]);
    }

    public function test_approval_empty_array_rejected_when_required(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'A', 'options' => ['X', 'Y']],
        ], ['abstainable' => false]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submit($election, $ballot, $vote, [$components[0]->id => []])
            ->assertViewIs('vote-failed');
    }

    // ----------------------------------------------------------------
    // RankedChoice
    // ----------------------------------------------------------------

    public function test_ranked_scalar_value_is_rejected(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'RankedChoice', 'version' => 'v1', 'title' => 'R', 'options' => ['X', 'Y', 'Z']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submit($election, $ballot, $vote, [$components[0]->id => 'FAKE'])
            ->assertViewIs('vote-failed');

        $vote->refresh();
        $this->assertNull($vote->values);
    }

    public function test_ranked_duplicate_rank_is_rejected(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'RankedChoice', 'version' => 'v1', 'title' => 'R', 'options' => ['X', 'Y', 'Z']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submit($election, $ballot, $vote, [$components[0]->id => ['X', 'X', 'Y']])
            ->assertViewIs('vote-failed');
    }

    public function test_ranked_valid_ranking_is_accepted(): void
    {
        [$election, $ballot, $components] = $this->make([
            ['type' => 'RankedChoice', 'version' => 'v1', 'title' => 'R', 'options' => ['X', 'Y', 'Z']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submit($election, $ballot, $vote, [$components[0]->id => ['Z', 'X', 'Y']])
            ->assertViewIs('voted');

        $vote->refresh();
        $this->assertEquals(['Z', 'X', 'Y'], $vote->values[$components[0]->id]);
    }

    // ----------------------------------------------------------------
    // Abstain semantics for array components (abstain == empty selection)
    // ----------------------------------------------------------------

    public function test_abstaining_on_array_component_is_recorded_as_absence(): void
    {
        // On an abstainable election a voter may simply omit an array component;
        // it is not stored as a selection and contributes nothing to results.
        [$election, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'A', 'options' => ['X', 'Y']],
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q', 'options' => []],
        ], ['abstainable' => true]);

        $vote = Vote::factory()->forBallot($ballot)->create();

        // Answer only the YesNo component; omit the approval component entirely.
        $this->submit($election, $ballot, $vote, [$components[1]->id => 'yes'])
            ->assertViewIs('voted');

        $vote->refresh();
        $this->assertArrayNotHasKey($components[0]->id, $vote->values);
        $this->assertEquals('yes', $vote->values[$components[1]->id]);

        $results = app(BallotService::class)->calculateResults($ballot);
        // The approval component has no tallies (nobody selected anything).
        $this->assertEquals([], $results[$components[0]->id]['results']['state']);
    }
}
