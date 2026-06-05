<?php

namespace Tests\Feature\Voting;

use App\BallotComponents\YesNo\v1\YesNo;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BallotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BallotService::class);
    }

    protected function make(array $componentConfigs, array $ballotAttrs = []): array
    {
        $election = Election::factory()->create(['abstainable' => false]);
        $ballot = Ballot::factory()->create(array_merge([
            'election_id' => $election->id, 'active' => true, 'mode' => Ballot::MODE_BASIC,
        ], $ballotAttrs));
        $components = [];
        foreach ($componentConfigs as $c) {
            $components[] = BallotComponent::factory()->create(array_merge(['ballot_id' => $ballot->id], $c));
        }
        return [$election, $ballot, $components];
    }

    // ----------------------------------------------------------------
    // calculateResults
    // ----------------------------------------------------------------

    public function test_calculate_results_with_no_votes_returns_empty_components(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q', 'options' => []],
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'P', 'options' => ['A', 'B']],
        ]);

        $results = $this->service->calculateResults($ballot);

        $this->assertEquals(0, $results['_meta']['votes_cast']);
        foreach ($components as $component) {
            $this->assertEquals([], $results[$component->id]['results']['state']);
            $this->assertNull($results[$component->id]['results']['winner']);
        }
    }

    public function test_calculate_results_includes_component_meta(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve budget?', 'description' => 'desc', 'options' => []],
        ]);
        Vote::factory()->forBallot($ballot)->withValues([$components[0]->id => 'yes'])->create();

        $results = $this->service->calculateResults($ballot);

        $this->assertEquals('Approve budget?', $results[$components[0]->id]['title']);
        $this->assertEquals('YesNo', $results[$components[0]->id]['type']);
    }

    // ----------------------------------------------------------------
    // resultsCsv
    // ----------------------------------------------------------------

    public function test_results_csv_contains_titles_and_scalar_values(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Budget', 'options' => []],
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'President', 'options' => ['Ana', 'Bob']],
        ]);

        Vote::factory()->forBallot($ballot)->withValues([
            $components[0]->id => 'yes', $components[1]->id => 'Ana',
        ])->create();
        Vote::factory()->forBallot($ballot)->withValues([
            $components[0]->id => 'no', $components[1]->id => 'Bob',
        ])->create();

        $csv = $this->service->resultsCsv($ballot);

        $this->assertStringContainsString('Budget', $csv);
        $this->assertStringContainsString('President', $csv);
        $this->assertStringContainsString('yes', $csv);
        $this->assertStringContainsString('Ana', $csv);
        $this->assertStringContainsString('Bob', $csv);
        // Two cast votes -> header row + two data rows.
        $this->assertCount(3, array_filter(explode("\n", trim($csv))));
    }

    public function test_results_csv_joins_array_component_values(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'Colors', 'options' => ['Red', 'Green', 'Blue']],
        ]);

        Vote::factory()->forBallot($ballot)->withValues([
            $components[0]->id => ['Red', 'Blue'],
        ])->create();

        $csv = $this->service->resultsCsv($ballot);

        $this->assertStringContainsString('Red, Blue', $csv);
    }

    // ----------------------------------------------------------------
    // Component registry surface exposed through the service
    // ----------------------------------------------------------------

    public function test_get_ballot_types_lists_all_components(): void
    {
        $types = $this->service->getBallotTypes();

        $this->assertEqualsCanonicalizing(
            ['YesNo', 'FirstPastThePost', 'RankedChoice', 'ApprovalVote'],
            $types
        );
    }

    public function test_get_ballot_versions(): void
    {
        $this->assertEquals(['v1'], $this->service->getBallotVersions('YesNo'));
        $this->assertEquals([], $this->service->getBallotVersions('DoesNotExist'));
    }

    public function test_get_component_tree_exposes_metadata_for_every_component(): void
    {
        $tree = $this->service->getComponentTree();

        foreach (['YesNo', 'FirstPastThePost', 'RankedChoice', 'ApprovalVote'] as $type) {
            $this->assertArrayHasKey($type, $tree);
            $this->assertArrayHasKey('v1', $tree[$type]);
            $meta = $tree[$type]['v1'];
            $this->assertArrayHasKey('needsOptions', $meta);
            $this->assertArrayHasKey('livewireForm', $meta);
            $this->assertArrayHasKey('strings', $meta);
            $this->assertArrayHasKey('optionsValidators', $meta);
        }

        // YesNo uses preset options (needsOptions false); RankedChoice uses a livewire form.
        $this->assertFalse($tree['YesNo']['v1']['needsOptions']);
        $this->assertTrue($tree['RankedChoice']['v1']['livewireForm']);
    }

    public function test_resolve_component_returns_the_right_instance(): void
    {
        $this->assertInstanceOf(YesNo::class, $this->service->resolveComponent('YesNo', 'v1'));
    }

    // ----------------------------------------------------------------
    // Validators
    // ----------------------------------------------------------------

    public function test_get_submission_validators_merges_every_component(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q1', 'options' => []],
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Q2', 'options' => ['A', 'B']],
        ]);

        $validators = $this->service->getSubmissionValidators($ballot);

        $this->assertArrayHasKey($components[0]->id, $validators);
        $this->assertArrayHasKey($components[1]->id, $validators);
    }

    public function test_get_partial_submission_validators_only_includes_submitted(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q1', 'options' => []],
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Q2', 'options' => ['A', 'B']],
        ]);

        $validators = $this->service->getPartialSubmissionValidators($ballot, [
            $components[1]->id => 'A',
        ]);

        $this->assertArrayNotHasKey($components[0]->id, $validators);
        $this->assertArrayHasKey($components[1]->id, $validators);
    }

    public function test_get_component_validators_for_single_component(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q1', 'options' => []],
        ]);

        $validators = $this->service->getComponentValidators($components[0]);

        $this->assertArrayHasKey($components[0]->id, $validators);
    }
}
