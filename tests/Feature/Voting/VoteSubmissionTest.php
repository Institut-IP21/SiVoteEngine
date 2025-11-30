<?php

namespace Tests\Feature\Voting;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class VoteSubmissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an election with a ballot and components for testing.
     */
    protected function createElectionWithBallot(array $componentConfigs = [], array $electionAttrs = [], array $ballotAttrs = []): array
    {
        $election = Election::factory()->create(array_merge([
            'abstainable' => false,
        ], $electionAttrs));

        $ballot = Ballot::factory()->create(array_merge([
            'election_id' => $election->id,
            'active' => true,
            'mode' => Ballot::MODE_BASIC,
        ], $ballotAttrs));

        $components = [];
        foreach ($componentConfigs as $config) {
            $components[] = BallotComponent::factory()->create(array_merge([
                'ballot_id' => $ballot->id,
            ], $config));
        }

        return [$election, $ballot, $components];
    }

    // ==========================================
    // POST /election/{election}/ballot/{ballot} (Basic Mode)
    // ==========================================

    /**
     * Test successful vote submission with valid code and selection.
     */
    public function test_submit_vote_success(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('voted');
    }

    /**
     * Test that invalid/nonexistent vote codes are rejected.
     */
    public function test_submit_vote_invalid_code_rejected(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $component = $components[0];
        $fakeCode = Uuid::uuid4()->toString();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $fakeCode,
            $component->id => 'yes',
        ]);

        // Should return 404 view for invalid code
        $response->assertStatus(200);
        $response->assertViewIs('404');
    }

    /**
     * Test that vote code for a different ballot is rejected.
     */
    public function test_submit_vote_wrong_ballot_code_rejected(): void
    {
        // Create two separate ballots
        [$election1, $ballot1, $components1] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        [$election2, $ballot2, $components2] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        // Create vote for ballot1
        $vote = Vote::factory()->forBallot($ballot1)->create();

        // Try to use it on ballot2 - should be rejected
        $response = $this->post("/election/{$election2->id}/ballot/{$ballot2->id}", [
            'code' => $vote->id,
            $components2[0]->id => 'yes',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('404');
    }

    /**
     * Test that voting on inactive ballot is rejected.
     */
    public function test_submit_vote_inactive_ballot_rejected(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['active' => false]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('ballot-expired');
    }

    /**
     * Test that voting on finished ballot is rejected.
     */
    public function test_submit_vote_finished_ballot_rejected(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['active' => false, 'finished' => true]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('ballot-expired');
    }

    /**
     * Test that missing vote code fails validation.
     */
    public function test_submit_vote_missing_code_rejected(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $component = $components[0];

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            $component->id => 'yes',
        ]);

        // Missing code should return 404 view (code lookup fails)
        $response->assertStatus(200);
        $response->assertViewIs('404');
    }

    /**
     * Test that invalid option value fails validation.
     */
    public function test_submit_vote_invalid_selection_rejected(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'invalid_option',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('vote-failed');
    }

    /**
     * Test that missing required component vote fails validation.
     */
    public function test_submit_vote_missing_required_component_rejected(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $vote = Vote::factory()->forBallot($ballot)->create();

        // Submit without the component selection
        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('vote-failed');
    }

    // ==========================================
    // Session Mode Tests
    // ==========================================

    /**
     * Test that session endpoint rejects basic mode ballots.
     */
    public function test_submit_component_vote_basic_mode_rejected(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['mode' => Ballot::MODE_BASIC]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->withoutExceptionHandling();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Only SESSION ballots can vote this way');

        $this->post("/election/{$election->id}/ballot/{$ballot->id}/component", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);
    }

    /**
     * Test that basic mode endpoint rejects session ballots.
     */
    public function test_basic_mode_endpoint_rejects_session_ballot(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['mode' => Ballot::MODE_SESSION]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->withoutExceptionHandling();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can not vote SESSION ballots this way');

        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);
    }

    // ==========================================
    // Security & Input Validation Tests
    // ==========================================

    /**
     * Test that SQL injection attempts are sanitized.
     */
    public function test_submit_vote_sql_injection_attempt(): void
    {
        $options = ['Option A', 'Option B'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Choose one', 'options' => $options],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        // Attempt SQL injection in code field
        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => "' OR '1'='1",
            $component->id => 'Option A',
        ]);

        // Should fail gracefully (not crash)
        $response->assertStatus(200);
        $response->assertViewIs('404');
    }

    /**
     * Test that XSS attempts in options are rejected.
     */
    public function test_submit_vote_xss_attempt(): void
    {
        $options = ['Option A', 'Option B'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Choose one', 'options' => $options],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        // Attempt XSS in vote value
        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => '<script>alert("xss")</script>',
        ]);

        // Should be rejected as invalid option
        $response->assertStatus(200);
        $response->assertViewIs('vote-failed');
    }

    /**
     * Test that oversized payloads are handled gracefully.
     */
    public function test_submit_vote_oversized_payload_rejected(): void
    {
        $options = ['Option A', 'Option B'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Choose one', 'options' => $options],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        // Create an oversized payload
        $largeString = str_repeat('A', 100000);

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => $largeString,
        ]);

        // Should be rejected as invalid option
        $response->assertStatus(200);
        $response->assertViewIs('vote-failed');
    }

    /**
     * Test that malformed UUIDs are rejected.
     */
    public function test_submit_vote_invalid_uuid_format_rejected(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $component = $components[0];

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => 'not-a-valid-uuid',
            $component->id => 'yes',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('404');
    }

    /**
     * Test vote submission with multiple components.
     */
    public function test_submit_vote_multiple_components(): void
    {
        $options = ['Alice', 'Bob', 'Charlie'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Question 1', 'options' => []],
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Question 2', 'options' => $options],
        ]);

        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $components[0]->id => 'yes',
            $components[1]->id => 'Alice',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('voted');

        // Verify both values stored
        $vote->refresh();
        $this->assertEquals('yes', $vote->values[$components[0]->id]);
        $this->assertEquals('Alice', $vote->values[$components[1]->id]);
    }

    /**
     * Test vote submission with abstainable election.
     */
    public function test_submit_vote_with_abstain_option(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], ['abstainable' => true]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'abstain',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('voted');

        $vote->refresh();
        $this->assertEquals('abstain', $vote->values[$component->id]);
    }

    /**
     * Test that abstain is rejected when election is not abstainable.
     */
    public function test_submit_vote_abstain_rejected_when_not_allowed(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], ['abstainable' => false]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'abstain',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('vote-failed');
    }
}
