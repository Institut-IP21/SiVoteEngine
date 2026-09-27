<?php

declare(strict_types=1);

namespace Tests\Feature\BallotComponents;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 13 (service/CSV integration): OrderedList plugs into the same
 * BallotService surface every other component type uses — calculateResults
 * exposes its OrderedListResult::toArray() under the component id, and
 * resultsCsv lists each voter's ranked names via valuesToCsv (comma-joined,
 * omitting anything the voter never approved).
 */
class OrderedListResultsServiceTest extends TestCase
{
    use RefreshDatabase;

    private BallotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BallotService::class);
    }

    /**
     * @return array{0: Ballot, 1: BallotComponent}
     */
    private function make(): array
    {
        $election = Election::factory()->create(['abstainable' => false]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
            'mode' => Ballot::MODE_BASIC,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'OrderedList',
            'title' => 'Committee seats',
            'version' => 'v1',
            'options' => ['Ana', 'Bob', 'Cleo'],
            'settings' => ['seats' => 2],
        ]);

        return [$ballot, $component];
    }

    public function test_calculate_results_exposes_the_ordered_list_result_under_the_component_id(): void
    {
        [$ballot, $component] = $this->make();
        Vote::factory()->forBallot($ballot)->withValues([$component->id => ['Ana', 'Bob', 'Cleo']])->create();
        Vote::factory()->forBallot($ballot)->withValues([$component->id => ['Ana', 'Bob', 'Cleo']])->create();

        $results = $this->service->calculateResults($ballot);

        $this->assertSame('OrderedList', $results[$component->id]['type']);
        $this->assertSame('Committee seats', $results[$component->id]['title']);

        $res = $results[$component->id]['results'];
        $this->assertSame(2, $res['seats']);
        $this->assertSame('margins', $res['strength_measure']);
        $this->assertSame(['Ana', 'Bob'], $res['elected']);
        $this->assertSame(2, $res['accounting']['counted']);
    }

    public function test_results_csv_lists_ranked_names_and_omits_a_never_approved_candidate(): void
    {
        [$ballot, $component] = $this->make();

        Vote::factory()->forBallot($ballot)->withValues([$component->id => ['Bob', 'Ana']])->create();

        $csv = $this->service->resultsCsv($ballot);

        $this->assertStringContainsString('Committee seats', $csv);
        $this->assertStringContainsString('Bob, Ana', $csv);
        // Cleo was never approved on this ballot -> absent from the data row,
        // even though she's part of the component's roster.
        $rows = array_filter(explode("\n", trim($csv)));
        $dataRow = array_values($rows)[1];
        $this->assertStringNotContainsString('Cleo', $dataRow);
    }
}
