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

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'C'], $result['order']);
        $this->assertSame([['candidate' => 'C', 'from' => 'below_cut', 'reason' => 'min_quota:Sales']], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertTrue($result['binding']);
        $this->assertSame([], $qc->warnings());
    }

    public function test_min_quota_needs_two_promotions_preserves_order(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'excluded'),
            $this->entry('E', 5, 5, 'excluded'),
            $this->entry('F', 6, 6, 'excluded'),
        ];
        $categories = ['E' => 'Sales', 'F' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 2, 'binding' => true];

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
        $result = $qc->result();

        // Neither promotee (E, F) nor demotee (B, C) is in a band, so the
        // swap goes through: the two worst-ranked non-category members of
        // the cut (C, then B) are displaced by the two Sales candidates
        // below the cut, in their own natural relative order (E before F),
        // leaving A -- the sole non-swapped member -- in place.
        $this->assertSame(['A', 'E', 'F'], $result['order']);
        $this->assertSame(
            [
                ['candidate' => 'E', 'from' => 'below_cut', 'reason' => 'min_quota:Sales'],
                ['candidate' => 'F', 'from' => 'below_cut', 'reason' => 'min_quota:Sales'],
            ],
            $result['diff']
        );
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
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

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
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

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
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

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertTrue($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    public function test_max_quota_infeasible_not_enough_non_category_candidates(): void
    {
        // 3 seats, 2 of them Sales; no non-Sales candidate exists anywhere in
        // the roster -- need=1 promotion, but 0 are available below cut.
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'excluded'),
        ];
        $categories = ['A' => 'Sales', 'B' => 'Sales', 'D' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'max', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
        $result = $qc->result();

        $this->assertSame(['A', 'B', 'C'], $result['order']);
        $this->assertTrue($result['infeasible']);
        $this->assertNotSame([], $qc->warnings());
    }

    /**
     * A contested cut the min quota does not depend on: {A = B} genuinely
     * tied for the single seat, A is the only Sales candidate, min Sales 1.
     * A first -> [A] already satisfies it; B first -> B is demoted and A
     * promoted -> [A]. Every resolution agrees, so the slate is final.
     * (Replaces a fixture with a contested cut but no band, which
     * PositionResolver can never produce.)
     */
    public function test_min_quota_resolves_a_contested_cut_it_does_not_depend_on(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'contested'),
            $this->entry('B', 1, 2, 'contested'),
        ];
        $bands = [
            [
                'candidates' => ['A', 'B'],
                'span' => [1, 2],
                'internal_constraints' => [],
                'head_to_head' => ['A' => ['B' => 0], 'B' => ['A' => 0]],
                'affects_cutoff' => true,
            ],
        ];
        $categories = ['A' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, $bands, $categories, $quota, 1);
        $result = $qc->result();

        $this->assertSame(['A'], $result['order']);
        $this->assertSame(['A'], $result['seated']);
        $this->assertSame([], $result['contested']);
        $this->assertSame(['A' => 1], $result['positions']);
        $this->assertSame([['candidate' => 'A', 'from' => 'contested', 'reason' => 'min_quota:Sales']], $result['diff']);
        $this->assertFalse($result['provisional']);
        $this->assertFalse($result['infeasible']);
        $this->assertSame([], $qc->warnings());
    }

    /**
     * A contested cut the min quota DOES depend on: {A = B} tied for one
     * seat, both Sales, min Sales 1 -- whichever wins is seated; nothing
     * certain.
     */
    public function test_min_quota_surfaces_a_contested_cut_it_depends_on(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'contested'),
            $this->entry('B', 1, 2, 'contested'),
            $this->entry('C', 3, 3, 'excluded'),
        ];
        $bands = [
            [
                'candidates' => ['A', 'B'],
                'span' => [1, 2],
                'internal_constraints' => [],
                'head_to_head' => ['A' => ['B' => 0], 'B' => ['A' => 0]],
                'affects_cutoff' => true,
            ],
        ];
        $categories = ['A' => 'Sales', 'B' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true];

        $qc = new QuotaCorrector($ranking, $bands, $categories, $quota, 1);
        $result = $qc->result();

        $this->assertSame([], $result['order']);
        $this->assertSame([], $result['seated']);
        $this->assertSame(['A', 'B'], $result['contested']);
        $this->assertSame([], $result['positions']);
        $this->assertTrue($result['provisional']);
        $this->assertFalse($result['infeasible']);
        $this->assertNotSame([], $qc->warnings());
    }

    public function test_ambiguity_guard_surfaces_when_promotee_is_in_a_band(): void
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

        $qc = new QuotaCorrector($ranking, $bands, $categories, $quota, 2);
        $result = $qc->result();

        // B is surely demoted; C and D are genuinely tied for the promotion.
        $this->assertSame(['A'], $result['order']);
        $this->assertSame(['A'], $result['seated']);
        $this->assertSame(['C', 'D'], $result['contested']);
        $this->assertSame(['A' => 1], $result['positions']);
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

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
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

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    public function test_max_quota_count_zero_excludes_all_in_category(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
            $this->entry('D', 4, 4, 'excluded'),
        ];
        $categories = ['A' => 'Sales', 'B' => 'Sales', 'C' => 'Eng', 'D' => 'Eng'];
        $quota = ['category' => 'Sales', 'type' => 'max', 'count' => 0, 'binding' => true];

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['C', 'D'], $result['order']);
        $this->assertSame(
            [
                ['candidate' => 'C', 'from' => 'below_cut', 'reason' => 'max_quota:Sales'],
                ['candidate' => 'D', 'from' => 'below_cut', 'reason' => 'max_quota:Sales'],
            ],
            $result['diff']
        );
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    public function test_max_quota_count_zero_infeasible_when_not_enough_replacements(): void
    {
        // 3 seats, all Sales in the cut; only 2 non-Sales candidates exist in
        // the whole roster -- excluding Sales entirely is impossible.
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'excluded'),
            $this->entry('E', 5, 5, 'excluded'),
        ];
        $categories = ['A' => 'Sales', 'B' => 'Sales', 'C' => 'Sales', 'D' => 'Eng', 'E' => 'Eng'];
        $quota = ['category' => 'Sales', 'type' => 'max', 'count' => 0, 'binding' => true];

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
        $result = $qc->result();

        $this->assertSame(['A', 'B', 'C'], $result['order']);
        $this->assertTrue($result['infeasible']);
        $this->assertNotSame([], $qc->warnings());
    }

    /**
     * Same fixture as test_min_quota_needs_one_promotion, only `binding` is
     * false. The REAL advisory effect -- the corrected order is still
     * computed and reported exactly as if binding -- is asserted here, not
     * just the `binding` passthrough flag.
     */
    public function test_advisory_quota_is_not_binding(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
        ];
        $categories = ['C' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => false];

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertFalse($result['binding']);
        $this->assertSame(['A', 'C'], $result['order']);
        $this->assertSame([['candidate' => 'C', 'from' => 'below_cut', 'reason' => 'min_quota:Sales']], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    /**
     * Edge case: min count(3) > seats(2), structurally impossible -- NOT
     * because the category is scarce overall (there are 3 category members
     * in the whole roster: C, D, E) but because only 2 seats exist to hold
     * them. `count($demoteesEntries) < $need` catches this unconditionally:
     * demoteesEntries can never exceed `seats` members, and need = count -
     * n > seats - n = max possible demotees whenever count > seats. Must be
     * reported infeasible, never a partial/guessed promotion.
     */
    public function test_min_quota_count_exceeding_seats_is_structurally_infeasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
            $this->entry('D', 4, 4, 'excluded'),
            $this->entry('E', 5, 5, 'excluded'),
        ];
        $categories = ['C' => 'Sales', 'D' => 'Sales', 'E' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'min', 'count' => 3, 'binding' => true];

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertTrue($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    /**
     * Edge case: max count == seats can never bind, because `n` (in-cut,
     * in-category members) can never exceed `seats`. Trivially satisfied
     * even in the extreme where every seat-holder is in the target category.
     */
    public function test_max_quota_count_equal_to_seats_is_trivially_satisfied(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
        ];
        $categories = ['A' => 'Sales', 'B' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'max', 'count' => 2, 'binding' => true];

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertSame([], $qc->warnings());
    }

    /**
     * Edge case: max count > seats — same reasoning, a fortiori. Never binds.
     */
    public function test_max_quota_count_greater_than_seats_is_trivially_satisfied(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
        ];
        $categories = ['A' => 'Sales', 'B' => 'Sales'];
        $quota = ['category' => 'Sales', 'type' => 'max', 'count' => 5, 'binding' => true];

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertSame([], $qc->warnings());
    }

    // --- alternation ("zipper") quota ---------------------------------------

    /**
     * @param array<string,string> $categories
     * @return array{category:string,type:string,count:int,binding:bool}
     */
    private function alternateQuota(array $categories, bool $binding = true): array
    {
        return ['category' => '', 'type' => 'alternate', 'count' => 0, 'binding' => $binding];
    }

    public function test_alternate_starts_with_group_a_when_natural_first_is_group_a(): void
    {
        // Natural #1 (A) is 'M', so the pattern is M,F,M,F. Natural order
        // does not already alternate (M,M,F,M) so B/C must swap.
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'elected'),
        ];
        $categories = ['A' => 'M', 'B' => 'M', 'C' => 'F', 'D' => 'M'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 4);
        $result = $qc->result();

        $this->assertSame('M', $categories[$result['order'][0]]);
        $this->assertSame(['A', 'C', 'B', 'D'], $result['order']);
        // D13 finding #2: B and C swap within the top-K, so both get a diff
        // row now (not just below-cut promotions) -- `order` is unchanged.
        $this->assertSame(
            [
                ['candidate' => 'C', 'from' => 'natural:3', 'reason' => 'alternate'],
                ['candidate' => 'B', 'from' => 'natural:2', 'reason' => 'alternate'],
            ],
            $result['diff']
        );
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertSame([], $qc->warnings());
    }

    public function test_alternate_starts_with_group_b_when_natural_first_is_group_b(): void
    {
        // Mirror of the above with the natural #1 (X) in the OTHER category
        // ('F'): the pattern must start F,M,F,M, not the other way round.
        $ranking = [
            $this->entry('X', 1, 1, 'elected'),
            $this->entry('Y', 2, 2, 'elected'),
            $this->entry('Z', 3, 3, 'elected'),
            $this->entry('W', 4, 4, 'elected'),
        ];
        $categories = ['X' => 'F', 'Y' => 'F', 'Z' => 'M', 'W' => 'M'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 4);
        $result = $qc->result();

        $this->assertSame('F', $categories[$result['order'][0]]);
        $this->assertSame(['X', 'Z', 'Y', 'W'], $result['order']);
        // D13 finding #2: Y and Z swap within the top-K.
        $this->assertSame(
            [
                ['candidate' => 'Z', 'from' => 'natural:3', 'reason' => 'alternate'],
                ['candidate' => 'Y', 'from' => 'natural:2', 'reason' => 'alternate'],
            ],
            $result['diff']
        );
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    public function test_alternate_clean_alternation_with_ample_both_groups_reorders_within_top_k(): void
    {
        // Same shape as the group-A-first case: no promotion across the
        // cut, purely a within-top-K reorder (B and C swap) to satisfy the
        // alternating pattern.
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'elected'),
        ];
        $categories = ['A' => 'M', 'B' => 'M', 'C' => 'F', 'D' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 4);
        $result = $qc->result();

        $this->assertSame(['A', 'C', 'B', 'D'], $result['order']);
        // D13 finding #2: the diff names the moved candidates (B, C), even
        // though neither entered from below the cut.
        $this->assertNotSame([], $result['diff']);
        $this->assertSame(
            [
                ['candidate' => 'C', 'from' => 'natural:3', 'reason' => 'alternate'],
                ['candidate' => 'B', 'from' => 'natural:2', 'reason' => 'alternate'],
            ],
            $result['diff']
        );
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    /**
     * Only one 'F' exists in the whole roster; the M,F,M,F pattern demands
     * two. The second F slot must fall back to M (the owner's run-out
     * rule) rather than ever going infeasible -- a full slate every time.
     */
    public function test_alternate_one_group_exhausted_fills_remaining_seats_from_the_other_group(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'elected'),
        ];
        $categories = ['A' => 'M', 'B' => 'M', 'C' => 'M', 'D' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 4);
        $result = $qc->result();

        $this->assertSame(['A', 'D', 'B', 'C'], $result['order']);
        // D13 finding #2: D, B and C all seat at a different position than
        // their natural rank (D promoted early by run-out; B/C pushed back),
        // so all three now get a diff row -- `order` is unchanged.
        $this->assertSame(
            [
                ['candidate' => 'D', 'from' => 'natural:4', 'reason' => 'alternate'],
                ['candidate' => 'B', 'from' => 'natural:2', 'reason' => 'alternate'],
                ['candidate' => 'C', 'from' => 'natural:3', 'reason' => 'alternate'],
            ],
            $result['diff']
        );
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertSame([], $qc->warnings());
    }

    /**
     * The natural top-3 is all 'M' (no 'F' at all in the cut); the sole 'F'
     * candidate sits below the cut and must be pulled up to satisfy
     * position 2 of the M,F,M pattern -- expressed as a promotion, exactly
     * like a min/max quota promotion (from:'below_cut').
     */
    public function test_alternate_promotes_from_below_cut_when_top_k_lacks_the_other_group(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'excluded'),
        ];
        $categories = ['A' => 'M', 'B' => 'M', 'C' => 'M', 'D' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
        $result = $qc->result();

        $this->assertSame(['A', 'D', 'B'], $result['order']);
        // D13 finding #2: B also moves (natural rank 2 -> seated rank 3), so
        // it now gets a diff row alongside D's below-cut promotion. C, which
        // is pushed out of the order entirely, gets none -- consistent with
        // applyMin/applyMax never tracking a plain demotion.
        $this->assertSame(
            [
                ['candidate' => 'D', 'from' => 'below_cut', 'reason' => 'alternate'],
                ['candidate' => 'B', 'from' => 'natural:2', 'reason' => 'alternate'],
            ],
            $result['diff']
        );
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
    }

    public function test_alternate_exactly_one_category_is_infeasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
        ];
        $categories = ['A' => 'M', 'B' => 'M'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertTrue($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    public function test_alternate_more_than_two_categories_is_infeasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
        ];
        $categories = ['A' => 'M', 'B' => 'F', 'C' => 'X'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
        $result = $qc->result();

        $this->assertSame(['A', 'B', 'C'], $result['order']);
        $this->assertTrue($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    /**
     * B carries no category tag at all -- alternation never guesses at an
     * untagged seated candidate's group, even though only two categories
     * (M, F) otherwise exist among the rest of the roster.
     */
    public function test_alternate_uncategorised_seated_candidate_is_infeasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
        ];
        $categories = ['A' => 'M', 'C' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertTrue($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    /**
     * The REAL advisory effect: the natural top-3 is M,M,F (not already
     * alternating); with `binding:false` the zipper still computes and
     * reports the actual M,F,M reorder (A,C,B) -- not the trivial
     * already-alternating case -- proving the correction runs identically
     * whether or not it will become official.
     */
    public function test_alternate_advisory_is_not_binding(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
        ];
        $categories = ['A' => 'M', 'B' => 'M', 'C' => 'F'];
        $quota = $this->alternateQuota($categories, binding: false);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
        $result = $qc->result();

        $this->assertFalse($result['binding']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertSame(['A', 'C', 'B'], $result['order']);
        $this->assertSame(
            [
                ['candidate' => 'C', 'from' => 'natural:3', 'reason' => 'alternate'],
                ['candidate' => 'B', 'from' => 'natural:2', 'reason' => 'alternate'],
            ],
            $result['diff']
        );
    }

    /**
     * The M,F pattern's second slot needs "the highest-ranked unplaced F",
     * but the two candidates who could fill it (D, E) are genuinely tied
     * with each other (an unresolved band) -- the engine must surface this,
     * never pick D over E (or vice versa) merely by array order; only the
     * determined prefix is reported until the organization resolves the tie.
     */
    public function test_alternate_pick_needing_a_tied_candidate_stays_provisional_forever(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 5, 'excluded'),
            $this->entry('E', 4, 5, 'excluded'),
        ];
        $bands = [
            [
                'candidates' => ['D', 'E'],
                'span' => [4, 5],
                'internal_constraints' => [],
                'head_to_head' => ['D' => ['E' => 0], 'E' => ['D' => 0]],
                'affects_cutoff' => false,
            ],
        ];
        $categories = ['A' => 'M', 'B' => 'M', 'C' => 'M', 'D' => 'F', 'E' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, $bands, $categories, $quota, 3);
        $result = $qc->result();

        // Only the determined prefix is reported (D14): seat 1 is A; seat 2
        // needs an F and D/E are tied for it.
        $this->assertSame(['A'], $result['order']);
        // A (seat 1) and B (seat 3, moved from natural 2) are certain; D/E are
        // tied for seat 2.
        $this->assertSame(['A', 'B'], $result['seated']);
        $this->assertSame(['D', 'E'], $result['contested']);
        $this->assertSame(['A' => 1, 'B' => 3], $result['positions']);
        $this->assertSame([['candidate' => 'B', 'from' => 'natural:2', 'reason' => 'alternate']], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertTrue($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    public function test_alternate_seats_one_is_trivially_satisfied(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'excluded'),
        ];
        $categories = ['A' => 'M', 'B' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 1);
        $result = $qc->result();

        $this->assertSame(['A'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertSame([], $qc->warnings());
    }

    // --- D13 fix regressions (2026-09-27 Opus review, finding #1) ----------

    /**
     * D13 finding #1, regression test 1: an UNTAGGED also-ran sitting below
     * the seatable range must not force infeasible -- it is never reachable
     * by a `seats`-length two-group zipper. Only A and B (the natural top-2)
     * matter; D (untagged, rank 4) is correctly ignored.
     */
    public function test_alternate_untagged_below_cut_also_ran_is_feasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
            $this->entry('D', 4, 4, 'excluded'),
        ];
        // D is deliberately absent from $categories -- untagged.
        $categories = ['A' => 'M', 'B' => 'F', 'C' => 'M'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertFalse($result['infeasible']);
        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['provisional']);
        $this->assertSame([], $qc->warnings());
    }

    /**
     * D13 finding #1, regression test 2: same shape, but the also-ran below
     * the cut carries a THIRD category (X) instead of being untagged --
     * still not reachable by the zipper, still feasible.
     */
    public function test_alternate_third_category_below_cut_also_ran_is_feasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'excluded'),
            $this->entry('D', 4, 4, 'excluded'),
        ];
        $categories = ['A' => 'M', 'B' => 'F', 'C' => 'M', 'D' => 'X'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertFalse($result['infeasible']);
        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['provisional']);
        $this->assertSame([], $qc->warnings());
    }

    /**
     * D13 finding #1, regression test 3 (guard 4 positive control): the
     * third category (X) sits WITHIN the natural top-`seats` this time (D at
     * natural rank 3) -- unlike the previous two tests, D WOULD be elected
     * with no quota at all, so a 2-group (M/F) zipper cannot silently drop
     * it. Infeasible, even though the M/F pools (A,E / B,G) are individually
     * plentiful enough to fill all 3 seats (guard 3 alone would pass) --
     * guard 4 is what catches this.
     */
    public function test_alternate_third_category_within_top_seats_is_infeasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('D', 3, 3, 'elected'),
            $this->entry('E', 4, 4, 'excluded'),
            $this->entry('G', 5, 5, 'excluded'),
        ];
        $categories = ['A' => 'M', 'B' => 'F', 'D' => 'X', 'E' => 'M', 'G' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
        $result = $qc->result();

        $this->assertTrue($result['infeasible']);
        $this->assertSame(['A', 'B', 'D'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    /**
     * D13 finding #1, regression test 4 (guard 1): the natural #1 candidate
     * itself carries no category -- the start group is genuinely undefined,
     * so this fails honestly rather than falling back to B.
     */
    public function test_alternate_untagged_natural_first_is_infeasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
        ];
        // A is deliberately absent from $categories -- untagged.
        $categories = ['B' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 2);
        $result = $qc->result();

        $this->assertTrue($result['infeasible']);
        $this->assertSame(['A', 'B'], $result['order']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    /**
     * D13 finding #1, regression test 5 (guard 3): 4 seats, but only 3
     * candidates anywhere in the roster carry a group1/group2 tag (D is
     * untagged) -- the two pools can never fill all 4 seats between them.
     */
    public function test_alternate_not_enough_candidates_in_the_two_groups_is_infeasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'elected'),
        ];
        $categories = ['A' => 'M', 'B' => 'F', 'C' => 'M'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 4);
        $result = $qc->result();

        $this->assertTrue($result['infeasible']);
        $this->assertSame(['A', 'B', 'C', 'D'], $result['order']);
        $this->assertFalse($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }

    /**
     * D13 review note: the start-group tie was already handled by the
     * general `inBand` check in the fill loop (the very first pick is
     * always the highest-natural-ranked group1 member) but was untested.
     * Here A and B are genuinely tied for the top spot -- whichever the
     * fill picks first lands on a band member, so alternation must surface
     * provisional rather than silently pick a start group.
     */
    // --- D13.1 fix regressions (2026-09-27 2nd Opus review, new blocker) ---

    /**
     * D13.1 regression test 1 (the exact G2 repro): the natural top-3 is
     * monochromatic (all 'M') so the second group must come from below the
     * cut -- but TWO other tagged categories exist there (`X` at rank 4,
     * `F` at rank 5). Letting `group2` float to whichever ranks highest
     * (`X`) would silently seat a third-category candidate as a BINDING
     * slate with no warning -- the exact blocker. Must refuse: infeasible,
     * the new ambiguous-second-group warning, natural order untouched.
     */
    public function test_alternate_monochromatic_top_k_with_two_below_cut_categories_is_infeasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('E', 4, 4, 'excluded'),
            $this->entry('D', 5, 5, 'excluded'),
        ];
        $categories = ['A' => 'M', 'B' => 'M', 'C' => 'M', 'E' => 'X', 'D' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 3);
        $result = $qc->result();

        $this->assertTrue($result['infeasible']);
        $this->assertSame(['A', 'B', 'C'], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['provisional']);
        $this->assertSame(
            [__('components.orderedlist.alternate_warn_ambiguous_second_group')],
            $qc->warnings()
        );
    }

    /**
     * D13.1 regression test 2 (the central rebalance case, MUST stay
     * feasible): the natural top-4 is monochromatic (all 'M'), but only ONE
     * other tagged category ('F') exists below the cut -- unambiguous, so
     * `group2 = 'F'` and the zipper proceeds exactly as the D13 fix
     * intended (an all-male top-K rebalanced against a single below-cut
     * group).
     */
    public function test_alternate_monochromatic_top_k_with_one_below_cut_category_is_feasible(): void
    {
        $ranking = [
            $this->entry('A', 1, 1, 'elected'),
            $this->entry('B', 2, 2, 'elected'),
            $this->entry('C', 3, 3, 'elected'),
            $this->entry('D', 4, 4, 'elected'),
            $this->entry('E', 5, 5, 'excluded'),
            $this->entry('F', 6, 6, 'excluded'),
        ];
        $categories = ['A' => 'M', 'B' => 'M', 'C' => 'M', 'D' => 'M', 'E' => 'F', 'F' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, [], $categories, $quota, 4);
        $result = $qc->result();

        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['provisional']);
        $this->assertSame(['A', 'E', 'B', 'F'], $result['order']);
        $this->assertSame('M', $categories[$result['order'][0]]);
        $movedCandidates = array_column($result['diff'], 'candidate');
        $this->assertContains('E', $movedCandidates);
        $this->assertContains('F', $movedCandidates);
    }

    public function test_alternate_start_group_tie_stays_provisional(): void
    {
        $ranking = [
            $this->entry('A', 1, 2, 'elected'),
            $this->entry('B', 1, 2, 'elected'),
        ];
        $bands = [
            [
                'candidates' => ['A', 'B'],
                'span' => [1, 2],
                'internal_constraints' => [],
                'head_to_head' => ['A' => ['B' => 0], 'B' => ['A' => 0]],
                'affects_cutoff' => false,
            ],
        ];
        $categories = ['A' => 'M', 'B' => 'F'];
        $quota = $this->alternateQuota($categories);

        $qc = new QuotaCorrector($ranking, $bands, $categories, $quota, 2);
        $result = $qc->result();

        // Start group itself undecided (A:M vs B:F tied for #1) -> nothing determined.
        $this->assertSame([], $result['order']);
        $this->assertSame([], $result['diff']);
        $this->assertFalse($result['infeasible']);
        $this->assertTrue($result['provisional']);
        $this->assertNotSame([], $qc->warnings());
    }
}
