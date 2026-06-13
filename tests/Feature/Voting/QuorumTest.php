<?php

namespace Tests\Feature\Voting;

use Illuminate\Testing\TestResponse;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuorumTest extends TestCase
{
    use RefreshDatabase;

    protected BallotService $ballotService;
    private string $token = '123123123';
    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ballotService = app(BallotService::class);
        $this->owner = fake()->uuid();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => $this->token, 'Owner' => $this->owner];
    }

    protected function createElectionWithBallot(array $componentConfigs = [], array $electionAttrs = [], array $ballotAttrs = []): array
    {
        $election = Election::factory()->create(array_merge([
            'abstainable' => false,
            'owner' => $this->owner,
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

    protected function submitVote(Election $election, Ballot $ballot, Vote $vote, array $selections): TestResponse
    {
        return $this->post("/election/{$election->id}/ballot/{$ballot->id}", array_merge([
            'code' => $vote->id,
        ], $selections));
    }

    // ==========================================
    // _meta quorum in calculateResults()
    // ==========================================

    public function test_results_include_quorum_meta_when_quorum_set(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['quorum' => 5]);

        $vote = Vote::factory()->forBallot($ballot)->create();
        $this->submitVote($election, $ballot, $vote, [$components[0]->id => 'yes']);

        $results = $this->ballotService->calculateResults($ballot);

        $this->assertArrayHasKey('_meta', $results);
        $this->assertEquals(5, $results['_meta']['quorum']);
        $this->assertEquals(1, $results['_meta']['votes_cast']);
        $this->assertFalse($results['_meta']['quorum_met']);
    }

    public function test_results_quorum_met_when_votes_exceed_quorum(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['quorum' => 3]);

        $votes = [];
        for ($i = 0; $i < 5; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        foreach ($votes as $vote) {
            $this->submitVote($election, $ballot, $vote, [$components[0]->id => 'yes']);
        }

        $results = $this->ballotService->calculateResults($ballot);

        $this->assertEquals(3, $results['_meta']['quorum']);
        $this->assertEquals(5, $results['_meta']['votes_cast']);
        $this->assertTrue($results['_meta']['quorum_met']);
    }

    public function test_results_quorum_not_met_when_votes_below_quorum(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['quorum' => 10]);

        $votes = [];
        for ($i = 0; $i < 3; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        foreach ($votes as $vote) {
            $this->submitVote($election, $ballot, $vote, [$components[0]->id => 'no']);
        }

        $results = $this->ballotService->calculateResults($ballot);

        $this->assertEquals(10, $results['_meta']['quorum']);
        $this->assertEquals(3, $results['_meta']['votes_cast']);
        $this->assertFalse($results['_meta']['quorum_met']);
    }

    public function test_results_quorum_met_when_votes_equal_quorum(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['quorum' => 3]);

        $votes = [];
        for ($i = 0; $i < 3; $i++) {
            $votes[] = Vote::factory()->forBallot($ballot)->create();
        }

        foreach ($votes as $vote) {
            $this->submitVote($election, $ballot, $vote, [$components[0]->id => 'yes']);
        }

        $results = $this->ballotService->calculateResults($ballot);

        $this->assertEquals(3, $results['_meta']['quorum']);
        $this->assertEquals(3, $results['_meta']['votes_cast']);
        $this->assertTrue($results['_meta']['quorum_met']);
    }

    public function test_results_quorum_met_when_quorum_null(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['quorum' => null]);

        $vote = Vote::factory()->forBallot($ballot)->create();
        $this->submitVote($election, $ballot, $vote, [$components[0]->id => 'yes']);

        $results = $this->ballotService->calculateResults($ballot);

        $this->assertArrayHasKey('_meta', $results);
        $this->assertNull($results['_meta']['quorum']);
        $this->assertEquals(1, $results['_meta']['votes_cast']);
        $this->assertTrue($results['_meta']['quorum_met']);
    }

    // ==========================================
    // API endpoint tests
    // ==========================================

    public function test_result_api_includes_quorum_meta(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['quorum' => 2, 'active' => false, 'finished' => true]);

        // Create a cast vote
        Vote::factory()->forBallot($ballot)->withValues([
            $components[0]->id => 'yes',
        ])->create();

        $response = $this->getJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/result",
            $this->authHeaders()
        );

        $response->assertSuccessful();

        $data = $response->json();
        $this->assertArrayHasKey('_meta', $data);
        $this->assertEquals(2, $data['_meta']['quorum']);
        $this->assertEquals(1, $data['_meta']['votes_cast']);
        $this->assertFalse($data['_meta']['quorum_met']);
    }

    public function test_result_view_shows_quorum_banner(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['quorum' => 1, 'active' => false, 'finished' => true]);

        Vote::factory()->forBallot($ballot)->withValues([
            $components[0]->id => 'yes',
        ])->create();

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertStatus(200);
        $response->assertViewIs('ballot-result');
        $response->assertSee('1 / 1');
    }

    public function test_ballot_update_can_set_quorum(): void
    {
        [$election, $ballot] = $this->createElectionWithBallot([], [], ['active' => false]);

        $response = $this->postJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/update",
            ['quorum' => 15],
            $this->authHeaders()
        );

        $response->assertSuccessful();
        $ballot->refresh();
        $this->assertEquals(15, $ballot->quorum);
    }
}
