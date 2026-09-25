<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use Tests\TestCase;

class QuotaCorrectorTest extends TestCase
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

    public function test_min_quota_needs_one_promotion(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
            $this->entry('D', 4, 4, 'excluded'),
        ];
        $categories = ['C' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, null, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'C'], $result['order']);
        $this->assertSame([['candidate' => 'C', 'from' => 'below_cut', 'reason' => 'min_quota:Sales']], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertTrue($result['binding']);
        $this->assertSame([], $qc->warnings());
    }

    public function test_min_quota_already_satisfied(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
            $this->entry('D', 4, 4, 'excluded'),
        ];
        $categories = ['A' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, null, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    public function test_max_quota_demotes_and_promotes(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'excluded'),
            $this->entry('E', 5, 5, 'excluded'),
        ];
        $categories = ['A' => 'Sales', 'B' => 'Sales', 'C' => 'Sales', 'D' => 'Eng', 'E' => 'Eng'];
        $quota = ['category' => 'Sales', 'type' => 'max', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, null, [], $categories, $quota, 3);
        $result = $qc->result();

        $this->assertSame(['A', 'D', 'E'], $result['order']);
        $this->assertSame(
            [
                ['candidate' => 'D', 'from' => 'below_cut', 'reason' => 'max_quota:Sales'],
                ['candidate' => 'E', 'from' => 'below_cut', 'reason' => 'max_quota:Sales'],
            ],
            $result['diff']
        );
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    public function test_min_quota_infeasible_not_enough_candidates(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
            $this->entry('D', 4, 4, 'excluded'),
        ];
        // Only D belongs to Sales anywhere in the roster; quota demands 2.
        $categories = ['D' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 2, 'binding' => true];

        $qc = new QuotaCorrector($ranking, null, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertTrue($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    public function test_deferred_when_cutoff_contested(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'contested'),
            $this->entry('B', 1, 3, 'contested'),
            $this->entry('C', 2, 3, 'excluded'),
        ];
        $cutoffDecision = [
            'remaining_seats' => 1,
            'candidates' => ['A', 'B', 'C'],
            'internal_constraints' => [],
            'head_to_head' => [],
        ];
        $categories = ['A' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, $cutoffDecision, [], $categories, $quota, 1);
        $result = $qc->result();

        $this->assertSame([], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertTrue($result['provisional']);
        $this->assertTrue($result['binding']);
        $this->assertNotSame([], $qc->warnings());
    }

    public function test_ambiguity_guard_defers_when_promotee_is_in_a_band(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 4, 'excluded'),
            $this->entry('D', 3, 4, 'excluded'),
        ];
        $bands = [
            [
                'candidates' => ['C', 'D'],
                'span' => [3, 4],
                'internal_constraints' => [],
                'head_to_head' => ['C' => ['D' => 0], 'D' => ['C' => 0]],
                'affects_cutoff' => false,
            ],
        ];
        $categories = ['C' => 'Sales', 'D' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, null, $bands, $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertTrue($result['provisional']);
        $this->assertFalse($result['infeasible']);
        $this->assertNotSame([], $qc->warnings());
    }

    public function test_category_not_among_candidate_categories_is_ignored(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
        ];
        $categories = ['A' => 'Eng'];
        $quota = ['category' => 'Ghost', 'type' => 'min', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, null, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    public function test_max_quota_trivially_satisfied_with_zero_in_cut_candidates(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
        ];
        // Nobody in the cut belongs to Sales; a max quota is trivially satisfied.
        $categories = ['C' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'max', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, null, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    public function test_advisory_quota_is_not_binding(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
        ];
        $categories = ['C' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => false];

        $qc = new QuotaCorrector($ranking, null, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertFalse($result['binding']);
    }
}
