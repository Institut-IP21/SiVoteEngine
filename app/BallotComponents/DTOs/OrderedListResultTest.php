<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

use Tests\TestCase;

class OrderedListResultTest extends TestCase
{
    public function test_empty_shape(): void
    {
        $r = OrderedListResult::empty(3)->toArray();

        $this->assertSame(3, $r['seats']);
        $this->assertSame(0, $r['accounting']['cast']);
        $this->assertSame(0, $r['accounting']['blank']);
        $this->assertSame(0, $r['accounting']['invalid_only']);
        $this->assertSame(0, $r['accounting']['counted']);
        $this->assertSame('natural', $r['official']);
        $this->assertNull($r['final']);
        $this->assertNull($r['cutoff_decision']);
        $this->assertNull($r['corrected']);
        $this->assertSame('margins', $r['strength_measure']);
        $this->assertSame([], $r['ranking']);
        $this->assertSame([], $r['elected']);
        $this->assertSame([], $r['bands']);
        $this->assertSame([], $r['resolutions']);
        $this->assertSame([], $r['lock_in_log']);
        $this->assertSame(['candidates' => [], 'matrix' => []], $r['pairwise']);
        $this->assertSame([], $r['warnings']);
    }

    public function test_full_round_trip(): void
    {
        $ranking = [
            ['candidate' => 'A', 'best_pos' => 1, 'worst_pos' => 1, 'determined' => true, 'status' => 'elected'],
        ];
        $bands = [
            [
                'candidates' => ['B', 'C'],
                'span' => [2, 3],
                'internal_constraints' => [],
                'head_to_head' => ['B' => ['C' => 1], 'C' => ['B' => 0]],
                'affects_cutoff' => false,
            ],
        ];
        $cutoffDecision = [
            'remaining_seats' => 1,
            'candidates' => ['B', 'C'],
            'internal_constraints' => [],
            'head_to_head' => ['B' => ['C' => 1], 'C' => ['B' => 0]],
        ];
        $resolutions = [
            [
                'cluster' => ['B', 'C'],
                'order' => ['B', 'C'],
                'comment' => 'Draw held.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ],
        ];
        $final = [
            'order' => [
                ['position' => 1, 'candidate' => 'A', 'tied' => false],
                ['position' => 2, 'candidate' => 'B', 'tied' => false],
                ['position' => 3, 'candidate' => 'C', 'tied' => false],
            ],
            'complete' => true,
        ];
        $corrected = [
            'order' => ['A', 'C'],
            'diff' => [
                ['candidate' => 'C', 'from' => 'below_cut', 'reason' => 'min_quota:Sales'],
            ],
            'infeasible' => false,
            'provisional' => false,
            'binding' => true,
        ];
        $lockInLog = [
            ['type' => 'locked', 'winner' => 'A', 'loser' => 'B', 'for' => 3, 'against' => 1, 'margin' => 2],
        ];
        $pairwise = [
            'candidates' => ['A', 'B', 'C'],
            'matrix' => ['A' => ['B' => 3, 'C' => 3], 'B' => ['A' => 1, 'C' => 2], 'C' => ['A' => 1, 'B' => 0]],
        ];
        $accounting = ['cast' => 5, 'blank' => 1, 'invalid_only' => 1, 'counted' => 3];
        $warnings = ['seats clamped to roster size'];

        $dto = new OrderedListResult(
            seats: 3,
            ranking: $ranking,
            elected: ['A'],
            bands: $bands,
            cutoffDecision: $cutoffDecision,
            resolutions: $resolutions,
            final: $final,
            corrected: $corrected,
            official: 'corrected',
            lockInLog: $lockInLog,
            pairwise: $pairwise,
            accounting: $accounting,
            warnings: $warnings,
        );

        $this->assertInstanceOf(ComponentResult::class, $dto);

        $arr = $dto->toArray();

        $this->assertSame(3, $arr['seats']);
        $this->assertSame('margins', $arr['strength_measure']);
        $this->assertSame($ranking, $arr['ranking']);
        $this->assertSame(['A'], $arr['elected']);
        $this->assertSame($bands, $arr['bands']);
        $this->assertSame($cutoffDecision, $arr['cutoff_decision']);
        $this->assertSame($resolutions, $arr['resolutions']);
        $this->assertSame($final, $arr['final']);
        $this->assertSame($corrected, $arr['corrected']);
        $this->assertSame('corrected', $arr['official']);
        $this->assertSame($lockInLog, $arr['lock_in_log']);
        $this->assertSame($pairwise, $arr['pairwise']);
        $this->assertSame($accounting, $arr['accounting']);
        $this->assertSame($warnings, $arr['warnings']);
        $this->assertSame(
            ['seats', 'strength_measure', 'ranking', 'elected', 'bands', 'cutoff_decision', 'resolutions', 'final', 'corrected', 'official', 'lock_in_log', 'pairwise', 'accounting', 'warnings'],
            array_keys($arr)
        );
    }
}
