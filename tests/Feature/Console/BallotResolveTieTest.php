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
    private function contestedCutoffBallot(): array
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
}
