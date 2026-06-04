<?php

namespace Tests\Feature\Contract;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultApiContractTest extends TestCase
{
    use RefreshDatabase;

    private string $token = '123123123';
    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = fake()->uuid();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => $this->token, 'Owner' => $this->owner];
    }

    private function createFinishedBallotWithVotes(): array
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => true,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'order' => 0,
            'active' => false,
            'finished' => true,
        ]);

        $vote = Vote::factory()->forBallot($ballot)->withValues([
            $component->id => $component->options[0],
        ])->create();

        return [$election, $ballot, $component, $vote];
    }

    public function test_result_endpoint_returns_component_results(): void
    {
        [$election, $ballot, $component] = $this->createFinishedBallotWithVotes();

        $response = $this->getJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/result",
            $this->authHeaders()
        );

        $response->assertSuccessful();

        $data = $response->json();
        // Result is returned directly (not wrapped in {data:...})
        // Keyed by component ID
        $this->assertArrayHasKey($component->id, $data);
        $this->assertArrayHasKey('results', $data[$component->id]);
        $this->assertArrayHasKey('title', $data[$component->id]);
        $this->assertArrayHasKey('type', $data[$component->id]);
    }

    public function test_votes_csv_returns_csv_string(): void
    {
        [$election, $ballot, $component] = $this->createFinishedBallotWithVotes();

        $response = $this->getJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/votes.csv",
            $this->authHeaders()
        );

        $response->assertSuccessful();

        // votesCsv wraps in {data: "csv-string"}
        $csv = $response->json('data');
        $this->assertIsString($csv);
        $this->assertNotEmpty($csv);
    }

    public function test_result_requires_finished_ballot(): void
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
            'finished' => false,
        ]);

        $response = $this->getJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/result",
            $this->authHeaders()
        );

        $response->assertStatus(403);
    }
}
