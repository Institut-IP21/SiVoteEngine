<?php

namespace Tests\Feature\Voting;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Session-mode voting: components are submitted one at a time to a dedicated
 * endpoint and accumulated onto the same vote.
 */
class SessionVotingTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSession(array $componentConfigs, array $electionAttrs = []): array
    {
        $election = Election::factory()->create(array_merge(['abstainable' => false], $electionAttrs));
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id, 'active' => true, 'mode' => Ballot::MODE_SESSION,
        ]);
        $components = [];
        foreach ($componentConfigs as $c) {
            $components[] = BallotComponent::factory()->create(array_merge(['ballot_id' => $ballot->id], $c));
        }
        return [$election, $ballot, $components];
    }

    protected function submitComponent(Election $election, Ballot $ballot, Vote $vote, array $selection)
    {
        return $this->post(
            "/election/{$election->id}/ballot/{$ballot->id}/component",
            array_merge(['code' => $vote->id], $selection)
        );
    }

    public function test_components_accumulate_across_submissions(): void
    {
        [$election, $ballot, $components] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q1', 'options' => []],
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q2', 'options' => []],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'yes']);
        $this->submitComponent($election, $ballot, $vote, [$components[1]->id => 'no']);

        $vote->refresh();
        $this->assertEquals('yes', $vote->values[$components[0]->id]);
        $this->assertEquals('no', $vote->values[$components[1]->id]);
    }

    public function test_resubmitting_a_component_updates_its_value_last_write_wins(): void
    {
        [$election, $ballot, $components] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q', 'options' => []],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'yes']);
        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'no']);

        $vote->refresh();
        $this->assertEquals('no', $vote->values[$components[0]->id]);
    }

    public function test_changing_one_component_preserves_the_others(): void
    {
        [$election, $ballot, $components] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q1', 'options' => []],
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q2', 'options' => []],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'yes']);
        $this->submitComponent($election, $ballot, $vote, [$components[1]->id => 'no']);
        // Change the first component's answer.
        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'no']);

        $vote->refresh();
        $this->assertEquals('no', $vote->values[$components[0]->id]);
        $this->assertEquals('no', $vote->values[$components[1]->id]);
    }

    public function test_successful_component_submission_redirects_back(): void
    {
        [$election, $ballot, $components] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q', 'options' => []],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'yes'])
            ->assertStatus(302);
    }

    public function test_invalid_value_in_session_is_rejected_and_not_stored(): void
    {
        [$election, $ballot, $components] = $this->makeSession([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Q', 'options' => ['A', 'B']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'NotAnOption'])
            ->assertViewIs('vote-failed');

        $vote->refresh();
        $this->assertNull($vote->values);
    }

    public function test_partial_validator_ignores_unsubmitted_components(): void
    {
        // Two required components but only one submitted: session mode validates
        // only what was sent, so the submission succeeds.
        [$election, $ballot, $components] = $this->makeSession([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Q1', 'options' => ['A', 'B']],
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Q2', 'options' => ['C', 'D']],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'A'])
            ->assertStatus(302);

        $vote->refresh();
        $this->assertEquals('A', $vote->values[$components[0]->id]);
        $this->assertArrayNotHasKey($components[1]->id, $vote->values);
    }

    public function test_session_endpoint_rejects_inactive_ballot(): void
    {
        [$election, $ballot, $components] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q', 'options' => []],
        ]);
        $ballot->active = false;
        $ballot->save();

        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->submitComponent($election, $ballot, $vote, [$components[0]->id => 'yes'])
            ->assertViewIs('ballot-expired');
    }
}
