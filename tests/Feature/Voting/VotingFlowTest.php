<?php

namespace Tests\Feature\Voting;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected BallotService $ballotService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ballotService = app(BallotService::class);
    }

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

    /**
     * Submit a vote and return the response.
     */
    protected function submitVote(Election $election, Ballot $ballot, Vote $vote, array $selections): \Illuminate\Testing\TestResponse
    {
        return $this->post("/election/{$election->id}/ballot/{$ballot->id}", array_merge([
            'code' => $vote->id,
        ], $selections));
    }

    // ==========================================
    // Basic Mode E2E Tests with Result Verification
    // ==========================================

    /**
     * Test complete voting flow with YesNo component including result calculation.
     */
    public function test_complete_voting_flow_yesno_component(): void
    {
        // Setup: Create election with YesNo ballot
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve the proposal?', 'options' => []],
        ]);

        $component = $components[0];

        // Create and cast 5 votes
        $votes = [];
        for ($i = 0; $i < 5; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // Submit: 3 yes, 2 no
        $this->submitVote($election, $ballot, $votes[0], [$component->id => 'yes'])->assertStatus(200);
        $this->submitVote($election, $ballot, $votes[1], [$component->id => 'yes'])->assertStatus(200);
        $this->submitVote($election, $ballot, $votes[2], [$component->id => 'yes'])->assertStatus(200);
        $this->submitVote($election, $ballot, $votes[3], [$component->id => 'no'])->assertStatus(200);
        $this->submitVote($election, $ballot, $votes[4], [$component->id => 'no'])->assertStatus(200);

        // Calculate results
        $results = $this->ballotService->calculateResults($ballot);

        // Verify results
        $this->assertArrayHasKey($component->id, $results);
        $componentResult = $results[$component->id]['results'];

        $this->assertEquals(3, $componentResult['state']['yes']);
        $this->assertEquals(2, $componentResult['state']['no']);
        $this->assertEquals(5, $componentResult['total_votes']);
        $this->assertEquals('yes', $componentResult['winner']);
        $this->assertEquals(['yes'], $componentResult['winners']);
    }

    /**
     * Test complete voting flow with FirstPastThePost component.
     */
    public function test_complete_voting_flow_firstpastthepost_component(): void
    {
        $options = ['Alice', 'Bob', 'Charlie'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Choose the candidate', 'options' => $options],
        ]);

        $component = $components[0];

        // Create 7 votes
        $votes = [];
        for ($i = 0; $i < 7; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // Submit: Alice=3, Bob=2, Charlie=2
        $this->submitVote($election, $ballot, $votes[0], [$component->id => 'Alice']);
        $this->submitVote($election, $ballot, $votes[1], [$component->id => 'Alice']);
        $this->submitVote($election, $ballot, $votes[2], [$component->id => 'Alice']);
        $this->submitVote($election, $ballot, $votes[3], [$component->id => 'Bob']);
        $this->submitVote($election, $ballot, $votes[4], [$component->id => 'Bob']);
        $this->submitVote($election, $ballot, $votes[5], [$component->id => 'Charlie']);
        $this->submitVote($election, $ballot, $votes[6], [$component->id => 'Charlie']);

        // Calculate results
        $results = $this->ballotService->calculateResults($ballot);
        $componentResult = $results[$component->id]['results'];

        $this->assertEquals(3, $componentResult['state']['Alice']);
        $this->assertEquals(2, $componentResult['state']['Bob']);
        $this->assertEquals(2, $componentResult['state']['Charlie']);
        $this->assertEquals(7, $componentResult['total_votes']);
        $this->assertEquals('Alice', $componentResult['winner']);
    }

    /**
     * Test complete voting flow with ApprovalVote component.
     */
    public function test_complete_voting_flow_approvalvote_component(): void
    {
        $options = ['Red', 'Green', 'Blue', 'Yellow'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'Select approved colors', 'options' => $options],
        ]);

        $component = $components[0];

        $votes = [];
        for ($i = 0; $i < 4; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // Vote 0: Red, Green
        // Vote 1: Red, Blue
        // Vote 2: Red, Green, Yellow
        // Vote 3: Blue, Yellow
        $this->submitVote($election, $ballot, $votes[0], [$component->id => ['Red', 'Green']]);
        $this->submitVote($election, $ballot, $votes[1], [$component->id => ['Red', 'Blue']]);
        $this->submitVote($election, $ballot, $votes[2], [$component->id => ['Red', 'Green', 'Yellow']]);
        $this->submitVote($election, $ballot, $votes[3], [$component->id => ['Blue', 'Yellow']]);

        // Calculate results
        $results = $this->ballotService->calculateResults($ballot);
        $componentResult = $results[$component->id]['results'];

        // Red should win with 3 approvals
        $this->assertEquals(3, $componentResult['state']['Red']);
        $this->assertEquals(2, $componentResult['state']['Green']);
        $this->assertEquals(2, $componentResult['state']['Blue']);
        $this->assertEquals(2, $componentResult['state']['Yellow']);
        $this->assertEquals('Red', $componentResult['winner']);
    }

    /**
     * Test complete voting flow with RankedChoice component.
     */
    public function test_complete_voting_flow_rankedchoice_component(): void
    {
        $options = ['Alpha', 'Beta', 'Gamma'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'RankedChoice', 'version' => 'v1', 'title' => 'Rank the options', 'options' => $options],
        ]);

        $component = $components[0];

        $votes = [];
        for ($i = 0; $i < 5; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // 3 voters prefer Alpha first, 2 prefer Beta first
        $this->submitVote($election, $ballot, $votes[0], [$component->id => ['Alpha', 'Beta', 'Gamma']]);
        $this->submitVote($election, $ballot, $votes[1], [$component->id => ['Alpha', 'Gamma', 'Beta']]);
        $this->submitVote($election, $ballot, $votes[2], [$component->id => ['Alpha', 'Beta', 'Gamma']]);
        $this->submitVote($election, $ballot, $votes[3], [$component->id => ['Beta', 'Alpha', 'Gamma']]);
        $this->submitVote($election, $ballot, $votes[4], [$component->id => ['Beta', 'Gamma', 'Alpha']]);

        // Calculate results
        $results = $this->ballotService->calculateResults($ballot);
        $componentResult = $results[$component->id]['results'];

        // Alpha should win (3 first-choice votes out of 5 = 60% > 50%)
        $this->assertArrayHasKey('rounds', $componentResult);
        $this->assertNotEmpty($componentResult['rounds']);

        // Check first round
        $firstRound = $componentResult['rounds'][0];
        $this->assertEquals(3, $firstRound['Alpha']);
        $this->assertEquals(2, $firstRound['Beta']);
    }

    /**
     * Test complete voting flow with multiple mixed components.
     */
    public function test_complete_voting_flow_multi_component_ballot(): void
    {
        $candidateOptions = ['Alice', 'Bob'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve budget?', 'options' => []],
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Choose president', 'options' => $candidateOptions],
        ]);

        $yesNoComponent = $components[0];
        $fptpComponent = $components[1];

        $votes = [];
        for ($i = 0; $i < 4; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // Submit mixed votes
        $this->submitVote($election, $ballot, $votes[0], [
            $yesNoComponent->id => 'yes',
            $fptpComponent->id => 'Alice',
        ]);
        $this->submitVote($election, $ballot, $votes[1], [
            $yesNoComponent->id => 'yes',
            $fptpComponent->id => 'Bob',
        ]);
        $this->submitVote($election, $ballot, $votes[2], [
            $yesNoComponent->id => 'no',
            $fptpComponent->id => 'Alice',
        ]);
        $this->submitVote($election, $ballot, $votes[3], [
            $yesNoComponent->id => 'yes',
            $fptpComponent->id => 'Alice',
        ]);

        // Calculate results
        $results = $this->ballotService->calculateResults($ballot);

        // Verify YesNo results
        $yesNoResult = $results[$yesNoComponent->id]['results'];
        $this->assertEquals(3, $yesNoResult['state']['yes']);
        $this->assertEquals(1, $yesNoResult['state']['no']);
        $this->assertEquals('yes', $yesNoResult['winner']);

        // Verify FPTP results
        $fptpResult = $results[$fptpComponent->id]['results'];
        $this->assertEquals(3, $fptpResult['state']['Alice']);
        $this->assertEquals(1, $fptpResult['state']['Bob']);
        $this->assertEquals('Alice', $fptpResult['winner']);
    }

    // ==========================================
    // Result Calculation Verification Tests
    // ==========================================

    /**
     * Test that multiple votes calculate correct winner.
     */
    public function test_multiple_votes_calculate_correct_winner(): void
    {
        $options = ['Candidate A', 'Candidate B', 'Candidate C'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Vote', 'options' => $options],
        ]);

        $component = $components[0];

        // Create 15 votes
        $votes = [];
        for ($i = 0; $i < 15; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // A=7, B=5, C=3 - Candidate A should win
        for ($i = 0; $i < 7; $i++) {
            $this->submitVote($election, $ballot, $votes[$i], [$component->id => 'Candidate A']);
        }
        for ($i = 7; $i < 12; $i++) {
            $this->submitVote($election, $ballot, $votes[$i], [$component->id => 'Candidate B']);
        }
        for ($i = 12; $i < 15; $i++) {
            $this->submitVote($election, $ballot, $votes[$i], [$component->id => 'Candidate C']);
        }

        $results = $this->ballotService->calculateResults($ballot);
        $componentResult = $results[$component->id]['results'];

        $this->assertEquals(15, $componentResult['total_votes']);
        $this->assertEquals('Candidate A', $componentResult['winner']);
        $this->assertEquals(['Candidate A'], $componentResult['winners']);
    }

    /**
     * Test that ties are correctly detected.
     */
    public function test_tie_detection_works(): void
    {
        $options = ['Option X', 'Option Y'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Vote', 'options' => $options],
        ]);

        $component = $components[0];

        // Create 4 votes
        $votes = [];
        for ($i = 0; $i < 4; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // 2 each - should be a tie
        $this->submitVote($election, $ballot, $votes[0], [$component->id => 'Option X']);
        $this->submitVote($election, $ballot, $votes[1], [$component->id => 'Option X']);
        $this->submitVote($election, $ballot, $votes[2], [$component->id => 'Option Y']);
        $this->submitVote($election, $ballot, $votes[3], [$component->id => 'Option Y']);

        $results = $this->ballotService->calculateResults($ballot);
        $componentResult = $results[$component->id]['results'];

        $this->assertEquals(2, $componentResult['state']['Option X']);
        $this->assertEquals(2, $componentResult['state']['Option Y']);
        $this->assertEquals('tie', $componentResult['winner']);
        $this->assertCount(2, $componentResult['winners']);
        $this->assertContains('Option X', $componentResult['winners']);
        $this->assertContains('Option Y', $componentResult['winners']);
    }

    /**
     * Test ranked choice elimination rounds work correctly.
     */
    public function test_ranked_choice_elimination_rounds(): void
    {
        $options = ['Alpha', 'Beta', 'Gamma'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'RankedChoice', 'version' => 'v1', 'title' => 'Rank', 'options' => $options],
        ]);

        $component = $components[0];

        // Create 5 votes where no one has majority initially
        $votes = [];
        for ($i = 0; $i < 5; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // Alpha=2, Beta=2, Gamma=1 (no majority)
        // After Gamma eliminated, their vote goes to Beta
        // Beta=3, Alpha=2 - Beta wins
        $this->submitVote($election, $ballot, $votes[0], [$component->id => ['Alpha', 'Beta', 'Gamma']]);
        $this->submitVote($election, $ballot, $votes[1], [$component->id => ['Alpha', 'Gamma', 'Beta']]);
        $this->submitVote($election, $ballot, $votes[2], [$component->id => ['Beta', 'Alpha', 'Gamma']]);
        $this->submitVote($election, $ballot, $votes[3], [$component->id => ['Beta', 'Gamma', 'Alpha']]);
        $this->submitVote($election, $ballot, $votes[4], [$component->id => ['Gamma', 'Beta', 'Alpha']]);

        $results = $this->ballotService->calculateResults($ballot);
        $componentResult = $results[$component->id]['results'];

        // Should have multiple rounds
        $this->assertArrayHasKey('rounds', $componentResult);
        $this->assertGreaterThanOrEqual(1, count($componentResult['rounds']));

        // First round should show the initial distribution
        $firstRound = $componentResult['rounds'][0];
        $this->assertEquals(2, $firstRound['Alpha']);
        $this->assertEquals(2, $firstRound['Beta']);
        $this->assertEquals(1, $firstRound['Gamma']);
    }

    // ==========================================
    // Session Mode Voting Tests
    // ==========================================

    /**
     * Test session mode component-by-component voting.
     */
    public function test_session_mode_vote_incremental_components(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Question 1', 'options' => []],
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Question 2', 'options' => []],
        ], [], ['mode' => Ballot::MODE_SESSION]);

        $vote = Vote::factory()->forBallot($ballot)->create();

        // Submit first component
        $this->post("/election/{$election->id}/ballot/{$ballot->id}/component", [
            'code' => $vote->id,
            $components[0]->id => 'yes',
        ]);

        $vote->refresh();
        $this->assertEquals('yes', $vote->values[$components[0]->id]);
        $this->assertArrayNotHasKey($components[1]->id, $vote->values);

        // Submit second component
        $this->post("/election/{$election->id}/ballot/{$ballot->id}/component", [
            'code' => $vote->id,
            $components[1]->id => 'no',
        ]);

        $vote->refresh();
        // Both values should be present
        $this->assertEquals('yes', $vote->values[$components[0]->id]);
        $this->assertEquals('no', $vote->values[$components[1]->id]);
    }

    /**
     * Test session mode results after all components voted.
     */
    public function test_session_mode_results_after_all_components(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q1', 'options' => []],
        ], [], ['mode' => Ballot::MODE_SESSION]);

        // Create multiple votes
        $votes = [];
        for ($i = 0; $i < 3; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // Submit votes via session mode
        $this->post("/election/{$election->id}/ballot/{$ballot->id}/component", [
            'code' => $votes[0]->id,
            $components[0]->id => 'yes',
        ]);
        $this->post("/election/{$election->id}/ballot/{$ballot->id}/component", [
            'code' => $votes[1]->id,
            $components[0]->id => 'yes',
        ]);
        $this->post("/election/{$election->id}/ballot/{$ballot->id}/component", [
            'code' => $votes[2]->id,
            $components[0]->id => 'no',
        ]);

        // Calculate results
        $results = $this->ballotService->calculateResults($ballot);
        $componentResult = $results[$components[0]->id]['results'];

        $this->assertEquals(2, $componentResult['state']['yes']);
        $this->assertEquals(1, $componentResult['state']['no']);
        $this->assertEquals('yes', $componentResult['winner']);
    }

    // ==========================================
    // Abstainable Election Tests
    // ==========================================

    /**
     * Test voting with abstain option across all component types.
     */
    public function test_voting_with_abstain_option(): void
    {
        $options = ['Option 1', 'Option 2'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q1', 'options' => []],
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Q2', 'options' => $options],
        ], ['abstainable' => true]);

        $votes = [];
        for ($i = 0; $i < 3; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        // Vote with abstain
        $this->submitVote($election, $ballot, $votes[0], [
            $components[0]->id => 'abstain',
            $components[1]->id => 'abstain',
        ])->assertViewIs('voted');

        $this->submitVote($election, $ballot, $votes[1], [
            $components[0]->id => 'yes',
            $components[1]->id => 'Option 1',
        ])->assertViewIs('voted');

        $this->submitVote($election, $ballot, $votes[2], [
            $components[0]->id => 'no',
            $components[1]->id => 'Option 2',
        ])->assertViewIs('voted');

        // Verify abstain votes are counted
        $results = $this->ballotService->calculateResults($ballot);

        $yesNoResult = $results[$components[0]->id]['results'];
        $this->assertArrayHasKey('abstain', $yesNoResult['state']);
        $this->assertEquals(1, $yesNoResult['state']['abstain']);

        $fptpResult = $results[$components[1]->id]['results'];
        $this->assertArrayHasKey('abstain', $fptpResult['state']);
        $this->assertEquals(1, $fptpResult['state']['abstain']);
    }

    /**
     * Test that abstain is rejected when election is not abstainable.
     */
    public function test_non_abstainable_election_rejects_abstain(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], ['abstainable' => false]);

        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->submitVote($election, $ballot, $vote, [
            $components[0]->id => 'abstain',
        ]);

        $response->assertViewIs('vote-failed');
    }

    // ==========================================
    // Result View Tests
    // ==========================================

    /**
     * Test that results page shows correctly after ballot is finished.
     */
    public function test_results_page_accessible_after_ballot_finished(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $vote = Vote::factory()->forBallot($ballot)->create();
        $this->submitVote($election, $ballot, $vote, [$components[0]->id => 'yes']);

        // Finish the ballot
        $ballot->active = false;
        $ballot->finished = true;
        $ballot->save();

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertStatus(200);
        $response->assertViewIs('ballot-result');
    }

    /**
     * Test that results page is forbidden before ballot is finished.
     */
    public function test_results_page_forbidden_before_finish(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertStatus(403);
    }
}
