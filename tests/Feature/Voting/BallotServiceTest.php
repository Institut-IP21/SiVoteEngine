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
            // D10 full roster: every declared option is present, seeded at 0 (YesNo
            // falls back to its yes/no preset). No votes -> all-zero, no winner.
            $state = $results[$component->id]['results']['state'];
            $this->assertSame(0, array_sum($state));
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

    /**
     * Regression: the raw per-vote CSV is seat-agnostic. A `settings.seats` value
     * on OrderedList/ApprovalVote must not change what resultsCsv() emits — it's
     * still exactly what each voter submitted, unaffected by tallying.
     */
    public function test_results_csv_round_trips_raw_values_for_ordered_list_and_multiwinner_approval(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'OrderedList', 'version' => 'v1', 'title' => 'Board election', 'options' => ['Ana', 'Bojan', 'Cveto'], 'settings' => ['seats' => 2]],
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'Committee', 'options' => ['A', 'B', 'C'], 'settings' => ['seats' => 2]],
        ]);

        Vote::factory()->forBallot($ballot)->withValues([
            $components[0]->id => ['Bojan', 'Ana', 'Cveto'],
            $components[1]->id => ['A', 'C'],
        ])->create();

        $csv = $this->service->resultsCsv($ballot);

        $this->assertStringContainsString('Bojan, Ana, Cveto', $csv);
        $this->assertStringContainsString('A, C', $csv);
    }

    // ----------------------------------------------------------------
    // resultsTallyCsv
    // ----------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function findCsvRow(string $csv, string $option): array
    {
        foreach (array_filter(explode("\n", trim($csv))) as $line) {
            $cells = str_getcsv($line);
            if (($cells[1] ?? null) === $option) {
                return $cells;
            }
        }

        $this->fail("CSV row for option '{$option}' not found in:\n{$csv}");
    }

    public function test_results_tally_csv_shows_the_elected_slate_and_ranks_for_ordered_list(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'OrderedList', 'version' => 'v1', 'title' => 'Board election', 'options' => ['Ana', 'Bojan', 'Cveto', 'Davor'], 'settings' => ['seats' => 3]],
        ]);

        // Unanimous ranking -> a clean, undisputed Schulze order (no ties at the cutoff).
        foreach (range(1, 3) as $ignored) {
            Vote::factory()->forBallot($ballot)->withValues([
                $components[0]->id => ['Ana', 'Bojan', 'Cveto', 'Davor'],
            ])->create();
        }

        $csv = $this->service->resultsTallyCsv($ballot);

        $ana = $this->findCsvRow($csv, 'Ana');
        $this->assertSame('yes', $ana[4]);
        $this->assertSame('1', $ana[5]);

        $cveto = $this->findCsvRow($csv, 'Cveto');
        $this->assertSame('yes', $cveto[4]);
        $this->assertSame('3', $cveto[5]);

        $davor = $this->findCsvRow($csv, 'Davor');
        $this->assertSame('no', $davor[4]);
        $this->assertSame('', $davor[5]);
    }

    public function test_results_tally_csv_shows_the_elected_set_and_a_contested_marker_at_a_tied_cutoff(): void
    {
        [, $ballot, $components] = $this->make([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'Committee', 'options' => ['A', 'B', 'C', 'D'], 'settings' => ['seats' => 2]],
        ]);

        // A clear leader (A), a tie for the last seat (B, C), and a clear loser (D).
        foreach (range(1, 5) as $ignored) {
            Vote::factory()->forBallot($ballot)->withValues([$components[0]->id => ['A']])->create();
        }
        foreach (range(1, 3) as $ignored) {
            Vote::factory()->forBallot($ballot)->withValues([$components[0]->id => ['B']])->create();
        }
        foreach (range(1, 3) as $ignored) {
            Vote::factory()->forBallot($ballot)->withValues([$components[0]->id => ['C']])->create();
        }
        Vote::factory()->forBallot($ballot)->withValues([$components[0]->id => ['D']])->create();

        $csv = $this->service->resultsTallyCsv($ballot);

        $a = $this->findCsvRow($csv, 'A');
        $this->assertSame('5', $a[2]);
        $this->assertSame('yes', $a[4]);
        $this->assertSame('1', $a[5]);

        // Genuinely tied for the last seat — never resolved into a fabricated winner.
        $b = $this->findCsvRow($csv, 'B');
        $this->assertSame('contested', $b[4]);
        $this->assertSame('', $b[5]);

        $c = $this->findCsvRow($csv, 'C');
        $this->assertSame('contested', $c[4]);
        $this->assertSame('', $c[5]);

        $d = $this->findCsvRow($csv, 'D');
        $this->assertSame('no', $d[4]);
    }

    public function test_results_tally_csv_has_a_header_row(): void
    {
        [, $ballot] = $this->make([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Budget', 'options' => []],
        ]);

        $csv = $this->service->resultsTallyCsv($ballot);
        $lines = array_filter(explode("\n", trim($csv)));
        $header = str_getcsv((string) reset($lines));

        $this->assertSame([
            __('ballot.tally_csv.question'),
            __('ballot.tally_csv.option'),
            __('ballot.tally_csv.count'),
            __('ballot.tally_csv.rate'),
            __('ballot.tally_csv.elected'),
            __('ballot.tally_csv.rank'),
        ], $header);
    }

    // ----------------------------------------------------------------
    // Component registry surface exposed through the service
    // ----------------------------------------------------------------

    public function test_get_ballot_types_lists_all_components(): void
    {
        $types = $this->service->getBallotTypes();

        $this->assertEqualsCanonicalizing(
            ['YesNo', 'FirstPastThePost', 'RankedChoice', 'ApprovalVote', 'OrderedList'],
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
