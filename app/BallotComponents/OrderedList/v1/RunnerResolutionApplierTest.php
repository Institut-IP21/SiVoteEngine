<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use Tests\TestCase;

class RunnerResolutionApplierTest extends TestCase
{
    /**
     * @return array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}
     */
    private function entry(string $candidate, int $best, int $worst, string $status): array
    {
        return [
            'candidate' => $candidate,
            'best_pos' => $best,
            'worst_pos' => $worst,
            'determined' => $best === $worst,
            'status' => $status,
        ];
    }

    /**
     * @param list<string> $roster
     * @param list<array{0:string,1:string}> $pairs
     * @return array<string,array<string,bool>>
     */
    private function reachableMap(array $roster, array $pairs): array
    {
        $map = [];
        foreach ($roster as $a) {
            foreach ($roster as $b) {
                $map[$a][$b] = false;
            }
        }
        foreach ($pairs as [$a, $b]) {
            $map[$a][$b] = true;
        }

        return $map;
    }

    public function test_valid_resolution_is_applied_and_complete(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'contested'),
            $this->entry('B', 1, 3, 'contested'),
            $this->entry('C', 2, 3, 'contested'),
        ];
        $band = [
            'candidates' => ['A', 'B', 'C'],
            'span' => [1, 3],
            'internal_constraints' => [['winner' => 'A', 'loser' => 'C']],
            'head_to_head' => [],
            'affects_cutoff' => true,
        ];
        $cutoffDecision = [
            'remaining_seats' => 1,
            'candidates' => ['A', 'B', 'C'],
            'internal_constraints' => [['winner' => 'A', 'loser' => 'C']],
            'head_to_head' => [],
        ];
        $reachable = $this->reachableMap(['A', 'B', 'C'], [['A', 'C']]);
        $resolutions = [
            [
                'cluster' => ['A', 'B', 'C'],
                'order' => ['A', 'B', 'C'],
                'comment' => 'Draw held.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ],
        ];

        $applier = new RunnerResolutionApplier($ranking, [$band], $cutoffDecision, $resolutions, $reachable, 1);

        $this->assertTrue($applier->applied());
        $result = $applier->result();
        $this->assertTrue($result['complete']);
        $this->assertSame(['position' => 1, 'candidate' => 'A', 'tied' => false], $result['order'][0]);
        $this->assertSame(['position' => 2, 'candidate' => 'B', 'tied' => false], $result['order'][1]);
        $this->assertSame(['position' => 3, 'candidate' => 'C', 'tied' => false], $result['order'][2]);
        $this->assertSame([], $applier->warnings());
    }

    public function test_resolution_contradicting_a_locked_fact_is_rejected(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'contested'),
            $this->entry('B', 1, 3, 'contested'),
            $this->entry('C', 2, 3, 'contested'),
        ];
        $band = [
            'candidates' => ['A', 'B', 'C'],
            'span' => [1, 3],
            'internal_constraints' => [['winner' => 'A', 'loser' => 'C']],
            'head_to_head' => [],
            'affects_cutoff' => true,
        ];
        $cutoffDecision = [
            'remaining_seats' => 1,
            'candidates' => ['A', 'B', 'C'],
            'internal_constraints' => [['winner' => 'A', 'loser' => 'C']],
            'head_to_head' => [],
        ];
        $reachable = $this->reachableMap(['A', 'B', 'C'], [['A', 'C']]);
        // C above A contradicts the locked fact that A beats C.
        $resolutions = [
            [
                'cluster' => ['A', 'B', 'C'],
                'order' => ['C', 'A', 'B'],
                'comment' => 'Bad draw.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ],
        ];

        $applier = new RunnerResolutionApplier($ranking, [$band], $cutoffDecision, $resolutions, $reachable, 1);

        $this->assertFalse($applier->applied());
        $this->assertNotSame([], $applier->warnings());

        $result = $applier->result();
        $this->assertFalse($result['complete']);
        foreach ($result['order'] as $row) {
            if (in_array($row['candidate'], ['A', 'B', 'C'], true)) {
                $this->assertTrue($row['tied']);
            }
        }
    }

    public function test_no_resolutions_means_not_applied(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'contested'),
            $this->entry('B', 1, 3, 'contested'),
            $this->entry('C', 2, 3, 'contested'),
        ];
        $band = [
            'candidates' => ['A', 'B', 'C'],
            'span' => [1, 3],
            'internal_constraints' => [],
            'head_to_head' => [],
            'affects_cutoff' => true,
        ];
        $cutoffDecision = [
            'remaining_seats' => 1,
            'candidates' => ['A', 'B', 'C'],
            'internal_constraints' => [],
            'head_to_head' => [],
        ];
        $reachable = $this->reachableMap(['A', 'B', 'C'], []);

        $applier = new RunnerResolutionApplier($ranking, [$band], $cutoffDecision, [], $reachable, 1);

        $this->assertFalse($applier->applied());
    }

    public function test_partial_resolution_leaves_the_other_band_tied(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'elected'),
            $this->entry('B', 1, 2, 'elected'),
            $this->entry('C', 3, 4, 'excluded'),
            $this->entry('D', 3, 4, 'excluded'),
        ];
        $bandTop = [
            'candidates' => ['A', 'B'],
            'span' => [1, 2],
            'internal_constraints' => [],
            'head_to_head' => [],
            'affects_cutoff' => false,
        ];
        $bandBottom = [
            'candidates' => ['C', 'D'],
            'span' => [3, 4],
            'internal_constraints' => [],
            'head_to_head' => [],
            'affects_cutoff' => false,
        ];
        $reachable = $this->reachableMap(['A', 'B', 'C', 'D'], []);
        $resolutions = [
            [
                'cluster' => ['A', 'B'],
                'order' => ['B', 'A'],
                'comment' => 'Coin toss.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ],
        ];

        $applier = new RunnerResolutionApplier($ranking, [$bandTop, $bandBottom], null, $resolutions, $reachable, 2);

        $this->assertTrue($applier->applied());
        $result = $applier->result();
        // bandBottom's span [3,4] starts after the seat cutoff (seats=2): it
        // is display-only ordering entanglement among already-excluded
        // candidates and does not block completeness -- only bandTop (span
        // [1,2], which can still hold a seat) does, and it IS resolved.
        $this->assertTrue($result['complete']);

        $byCandidate = [];
        foreach ($result['order'] as $row) {
            $byCandidate[$row['candidate']] = $row;
        }
        $this->assertFalse($byCandidate['A']['tied']);
        $this->assertFalse($byCandidate['B']['tied']);
        $this->assertSame(1, $byCandidate['B']['position']);
        $this->assertSame(2, $byCandidate['A']['position']);
        // Still individually unresolved/tied for display purposes, even
        // though it no longer blocks the DTO's "final.complete" flag.
        $this->assertTrue($byCandidate['C']['tied']);
        $this->assertTrue($byCandidate['D']['tied']);
    }

    public function test_a_band_entirely_below_the_cutoff_does_not_block_completeness(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('C', 2, 3, 'excluded'),
            $this->entry('D', 2, 3, 'excluded'),
        ];
        $bandBelowCutoff = [
            'candidates' => ['C', 'D'],
            'span' => [2, 3],
            'internal_constraints' => [],
            'head_to_head' => [],
            'affects_cutoff' => false,
        ];
        $reachable = $this->reachableMap(['A', 'C', 'D'], [['A', 'C'], ['A', 'D']]);

        $applier = new RunnerResolutionApplier($ranking, [$bandBelowCutoff], null, [], $reachable, 1);

        // No resolutions at all -- applied() stays false -- but the only
        // band there is starts after the K=1 cutoff, so it never blocks
        // completeness either way.
        $this->assertFalse($applier->applied());
        $this->assertTrue($applier->result()['complete']);
    }

    public function test_rejection_reasons_are_reported_distinctly(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'contested'),
            $this->entry('B', 1, 3, 'contested'),
            $this->entry('C', 2, 3, 'contested'),
        ];
        $band = [
            'candidates' => ['A', 'B', 'C'],
            'span' => [1, 3],
            'internal_constraints' => [['winner' => 'A', 'loser' => 'C']],
            'head_to_head' => [],
            'affects_cutoff' => true,
        ];
        $reachable = $this->reachableMap(['A', 'B', 'C'], [['A', 'C']]);

        // Not a permutation of the cluster (missing C, duplicates A).
        $notPermutation = [
            [
                'cluster' => ['A', 'B', 'C'],
                'order' => ['A', 'A', 'B'],
                'comment' => '',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ],
        ];
        $applier = new RunnerResolutionApplier($ranking, [$band], null, $notPermutation, $reachable, 1);
        $this->assertNotSame([], $applier->warnings());
        $this->assertStringContainsString('not a permutation of the tied set', $applier->warnings()[0]);

        // A permutation, but contradicts the locked A > C fact.
        $contradicts = [
            [
                'cluster' => ['A', 'B', 'C'],
                'order' => ['C', 'A', 'B'],
                'comment' => '',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ],
        ];
        $applier2 = new RunnerResolutionApplier($ranking, [$band], null, $contradicts, $reachable, 1);
        $this->assertNotSame([], $applier2->warnings());
        $this->assertStringContainsString('contradicts a locked head-to-head result', $applier2->warnings()[0]);
    }

    public function test_resolution_matching_no_current_cluster_is_ignored_with_warning(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
        ];
        $reachable = $this->reachableMap(['A', 'B'], [['A', 'B']]);
        $resolutions = [
            [
                'cluster' => ['X', 'Y'],
                'order' => ['X', 'Y'],
                'comment' => 'Stale resolution from a prior tally.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ],
        ];

        $applier = new RunnerResolutionApplier($ranking, [], null, $resolutions, $reachable, 2);

        $this->assertFalse($applier->applied());
        $this->assertNotSame([], $applier->warnings());
        $this->assertTrue($applier->result()['complete']);
    }
}
