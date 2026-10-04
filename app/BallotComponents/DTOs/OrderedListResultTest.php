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
        $this->assertNull($r['cutoff_decision']);
        $this->assertNull($r['corrected']);
        $this->assertSame('margins', $r['strength_measure']);
        $this->assertSame([], $r['ranking']);
        $this->assertSame([], $r['elected']);
        $this->assertSame([], $r['bands']);
        $this->assertSame(['strength' => [], 'winners' => []], $r['beatpath']);
        $this->assertSame(['candidates' => [], 'matrix' => []], $r['pairwise']);
        $this->assertSame([], $r['warnings']);
        $this->assertSame([], $r['official_order']);
        $this->assertSame([], $r['official_positions']);
        $this->assertSame([], $r['official_contested']);
        $this->assertFalse($r['final']);
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
        $corrected = [
            'order' => ['A', 'C'],
            'diff' => [
                ['candidate' => 'C', 'from' => 'below_cut', 'reason' => 'min_quota:Sales'],
            ],
            'infeasible' => false,
            'partly_infeasible' => false,
            'provisional' => false,
            'binding' => true,
            'too_complex' => false,
            'seated' => ['A', 'C'],
            'contested' => [],
            'positions' => ['A' => 1, 'C' => 2],
        ];
        $beatpath = [
            'strength' => ['A' => ['B' => 3, 'C' => 3], 'B' => ['A' => null, 'C' => 2], 'C' => ['A' => null, 'B' => null]],
            'winners' => [
                ['winner' => 'A', 'loser' => 'B', 'strength' => 3, 'path' => ['A', 'B']],
                ['winner' => 'A', 'loser' => 'C', 'strength' => 3, 'path' => ['A', 'C']],
                ['winner' => 'B', 'loser' => 'C', 'strength' => 2, 'path' => ['B', 'C']],
            ],
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
            corrected: $corrected,
            official: 'corrected',
            beatpath: $beatpath,
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
        $this->assertSame($corrected, $arr['corrected']);
        $this->assertSame('corrected', $arr['official']);
        // official_order follows the corrected slate when that is official.
        $this->assertSame(['A', 'C'], $arr['official_order']);
        $this->assertSame(['A' => 1, 'C' => 2], $arr['official_positions']);
        $this->assertSame([], $arr['official_contested']);
        $this->assertFalse($arr['final']); // seats 3 > 2 certain seats
        $this->assertSame($beatpath, $arr['beatpath']);
        $this->assertSame($pairwise, $arr['pairwise']);
        $this->assertSame($accounting, $arr['accounting']);
        $this->assertSame($warnings, $arr['warnings']);
        $this->assertSame(
            ['seats', 'strength_measure', 'ranking', 'elected', 'bands', 'cutoff_decision', 'corrected', 'official', 'official_order', 'official_positions', 'official_contested', 'final', 'beatpath', 'pairwise', 'accounting', 'warnings'],
            array_keys($arr)
        );
    }
}
