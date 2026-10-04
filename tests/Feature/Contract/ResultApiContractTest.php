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

    /**
     * @param list<list<string>> $rankings
     * @return array<string, mixed> the component's `results` payload
     */
    private function orderedListApiResults(array $rankings, int $seats): array
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => true,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['F1', 'M1', 'M2', 'M3', 'F2', 'F3'],
            'settings' => [
                'seats' => $seats,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'F3' => 'F', 'M1' => 'M', 'M2' => 'M', 'M3' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ],
        ]);
        foreach ($rankings as $ranking) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $ranking])->create();
        }

        $response = $this->getJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/result",
            $this->authHeaders()
        );
        $response->assertSuccessful();

        return $response->json("{$component->id}.results");
    }

    /**
     * web_app reads the OFFICIAL slate from these keys, never the natural
     * `elected`. The 2026-10-03 prod ballot: a binding alternation over a
     * natural cut contested across groups is official and final.
     */
    public function test_ordered_list_exposes_the_official_slate_contract(): void
    {
        $results = $this->orderedListApiResults([
            ['F1', 'M1', 'M2', 'M3', 'F2', 'F3'],
            ['F1', 'M1', 'M2', 'F2', 'M3', 'F3'],
        ], 4);

        $this->assertSame('corrected', $results['official']);
        $this->assertSame(['F1', 'M1', 'F2', 'M2'], $results['official_order']);
        $this->assertSame(['F1' => 1, 'M1' => 2, 'F2' => 3, 'M2' => 4], $results['official_positions']);
        $this->assertSame([], $results['official_contested']);
        $this->assertTrue($results['final']);
    }

    /**
     * A binding quota the votes cannot settle: the certain seats are the
     * official slate, the still-tied candidates are `official_contested`.
     */
    public function test_ordered_list_provisional_quota_exposes_seated_and_contested(): void
    {
        $results = $this->orderedListApiResults([
            ['F1', 'M1', 'F2', 'F3', 'M2', 'M3'],
            ['F1', 'M1', 'F3', 'F2', 'M2', 'M3'],
        ], 3);

        $this->assertSame('corrected', $results['official']);
        $this->assertSame(['F1', 'M1'], $results['official_order']);
        $this->assertSame(['F1' => 1, 'M1' => 2], $results['official_positions']);
        $this->assertEqualsCanonicalizing(['F2', 'F3'], $results['official_contested']);
        $this->assertFalse($results['final']);
    }
}
