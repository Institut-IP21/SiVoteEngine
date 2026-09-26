<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks Task 12's `ballot:resolve-tie` command: it only ever appends a
 * runner resolution that is (a) a permutation of an actually-surfaced tie
 * cluster and (b) consistent with every pairwise fact the votes already
 * locked in. Anything else is rejected without touching the column.
 *
 * The fixture reproduces PositionResolverTest::test_contested_cutoff_seats_one
 * end-to-end through real votes: roster [A, B, C], seats 1, only A > C
 * decisively locked (B ties both ways with A and with C), which surfaces one
 * band {A, B, C} that affects the seat cutoff — A must precede C in any
 * resolution, but B is unconstrained.
 */
class BallotResolveTieTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Ballot, 1: BallotComponent}
     */
    private function contestedCutoffBallot(bool $finished = true): array
    {
        $election = Election::factory()->create(['locale' => 'en', 'abstainable' => false]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => !$finished,
            'finished' => $finished,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['A', 'B', 'C'],
            'settings' => ['seats' => 1],
        ]);

        foreach (array_fill(0, 2, ['A', 'C']) as $ranking) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $ranking])->create();
        }
        foreach (array_fill(0, 2, ['B']) as $ranking) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $ranking])->create();
        }

        return [$ballot, $component];
    }

    /**
     * @param list<string> $options
     * @return array{0: Ballot, 1: BallotComponent}
     */
    private function cyclicTieBallot(array $options): array
    {
        $election = Election::factory()->create(['locale' => 'en', 'abstainable' => false]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => true,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => $options,
            'settings' => ['seats' => 1],
        ]);

        // A symmetric 3-voter Condorcet cycle (A>B>C, B>C>A, C>A>B): every
        // pairwise margin -- A>B, B>C, C>A -- is decisive but tied at 1, so
        // Ranked Pairs can lock none of them and the whole {A,B,C} band is
        // left fully unresolved, needing a manual --order.
        foreach ([['A', 'B', 'C'], ['B', 'C', 'A'], ['C', 'A', 'B']] as $ranking) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $ranking])->create();
        }

        return [$ballot, $component];
    }

    /**
     * Pins the roster-dedupe drift fix: `reachableFromVotes()` must dedupe
     * `$component->options` exactly like OrderedList::calculateResults does
     * (first occurrence wins), or a duplicate-label roster double-counts the
     * duplicated candidate's pairwise margins in PairwiseMatrix. On this
     * cycle, that shifts A>B and C>A from a tied margin of 1 (unlockable,
     * tied with B>C in the same cycle) to a margin of 2 -- letting Ranked
     * Pairs lock them after all and wrongly reject an --order such as
     * B,A,C that the correctly-deduped roster accepts. A component whose
     * options carry a duplicate label (bypassing the builder's `distinct`)
     * must validate --order identically to the same scenario with the
     * duplicate removed.
     */
    public function test_duplicate_labelled_roster_validates_identically_to_the_deduped_equivalent(): void
    {
        [, $duplicated] = $this->cyclicTieBallot(['A', 'B', 'C', 'A']);
        [, $clean] = $this->cyclicTieBallot(['A', 'B', 'C']);

        foreach ([$duplicated, $clean] as $component) {
            $this->artisan('ballot:resolve-tie', [
                'component' => $component->id,
                '--cluster' => 'A,B,C',
                '--order' => 'B,A,C',
                '--comment' => 'Coin toss witnessed by both agents.',
                '--by' => 'returning-officer',
            ])->assertExitCode(0);

            $component->refresh();
            $this->assertIsArray($component->runner_resolutions);
            $this->assertSame(['B', 'A', 'C'], $component->runner_resolutions[0]['order']);
        }
    }

    public function test_valid_order_is_recorded_and_completes_the_result(): void
    {
        [, $component] = $this->contestedCutoffBallot();

        $this->artisan('ballot:resolve-tie', [
            'component' => $component->id,
            '--cluster' => 'A,B,C',
            '--order' => 'B,A,C',
            '--comment' => 'Coin toss witnessed by both agents.',
            '--by' => 'returning-officer',
        ])->assertExitCode(0);

        $component->refresh();
        $this->assertIsArray($component->runner_resolutions);
        $this->assertCount(1, $component->runner_resolutions);
        $this->assertSame(['B', 'A', 'C'], $component->runner_resolutions[0]['order']);
        $this->assertSame('returning-officer', $component->runner_resolutions[0]['resolved_by']);

        $service = $this->app->make(\App\Services\BallotService::class);
        $results = $service->calculateResults($component->ballot);
        $final = $results[$component->id]['results']['final'];
        $this->assertNotNull($final);
        $this->assertTrue($final['complete']);
    }

    public function test_order_that_violates_a_locked_pair_is_rejected(): void
    {
        [, $component] = $this->contestedCutoffBallot();

        $this->artisan('ballot:resolve-tie', [
            'component' => $component->id,
            '--cluster' => 'A,B,C',
            // C decisively lost to A — placing C ahead of A contradicts the locked pair.
            '--order' => 'C,B,A',
            '--comment' => 'Invalid attempt.',
            '--by' => 'returning-officer',
        ])->assertExitCode(1);

        $component->refresh();
        $this->assertNull($component->runner_resolutions);
    }

    public function test_cluster_that_does_not_match_any_surfaced_tie_is_rejected(): void
    {
        [, $component] = $this->contestedCutoffBallot();

        $this->artisan('ballot:resolve-tie', [
            'component' => $component->id,
            '--cluster' => 'A,B',
            '--order' => 'A,B',
            '--comment' => 'No such cluster.',
            '--by' => 'returning-officer',
        ])->assertExitCode(1);

        $component->refresh();
        $this->assertNull($component->runner_resolutions);
    }

    public function test_resolution_is_refused_while_the_ballot_is_still_open(): void
    {
        [, $component] = $this->contestedCutoffBallot(finished: false);

        $this->artisan('ballot:resolve-tie', [
            'component' => $component->id,
            '--cluster' => 'A,B,C',
            '--order' => 'B,A,C',
            '--comment' => 'Too early.',
            '--by' => 'returning-officer',
        ])->assertExitCode(1);

        $component->refresh();
        $this->assertNull($component->runner_resolutions);
    }

    public function test_re_resolving_an_already_resolved_cluster_appends_and_the_latest_wins(): void
    {
        [, $component] = $this->contestedCutoffBallot();

        $this->artisan('ballot:resolve-tie', [
            'component' => $component->id,
            '--cluster' => 'A,B,C',
            '--order' => 'B,A,C',
            '--comment' => 'First draw.',
            '--by' => 'returning-officer',
        ])->assertExitCode(0);

        // PositionResolver recomputes the surfaced band fresh from the votes
        // each time (it doesn't know about prior resolutions), so the same
        // cluster can be matched and re-resolved with a different order.
        $this->artisan('ballot:resolve-tie', [
            'component' => $component->id,
            '--cluster' => 'A,B,C',
            '--order' => 'A,B,C',
            '--comment' => 'Runner reconsidered.',
            '--by' => 'returning-officer',
        ])->assertExitCode(0);

        $component->refresh();
        $this->assertCount(2, $component->runner_resolutions);
        $this->assertSame(['B', 'A', 'C'], $component->runner_resolutions[0]['order']);
        $this->assertSame(['A', 'B', 'C'], $component->runner_resolutions[1]['order']);

        // RunnerResolutionApplier matches the LAST stored resolution for a
        // cluster, so the second (most recent) order is the one that wins.
        $service = $this->app->make(\App\Services\BallotService::class);
        $results = $service->calculateResults($component->ballot);
        $final = $results[$component->id]['results']['final'];
        $this->assertNotNull($final);
        $this->assertTrue($final['complete']);
        $this->assertSame(['position' => 1, 'candidate' => 'A', 'tied' => false], $final['order'][0]);
    }
}
