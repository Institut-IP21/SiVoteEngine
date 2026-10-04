<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use Tests\TestCase;

/**
 * Property test for QuotaCorrector's exactness contract (D15): over random
 * strict partial orders (every shape of genuine tie), random categories,
 * quotas and seat counts, the corrector must report EXACTLY what every
 * tie resolution agrees on -- never a guess, never a withheld certainty.
 *
 * The oracle is deliberately independent of the production code: it
 * brute-forces every linear extension of the partial order and applies a
 * plain restatement of the quota rules (min / max / alternation) to each.
 * This is the test that would have caught the 2026-10-03 production bug
 * (alternation skipped on a cross-group cutoff tie) and the over-strict
 * min/max/alternation surfacing found in review.
 */
class QuotaCorrectorPropertyTest extends TestCase
{
    private const int TRIALS = 4000;

    public function test_quota_result_is_exactly_what_every_tie_resolution_agrees_on(): void
    {
        mt_srand(20261004);

        for ($trial = 0; $trial < self::TRIALS; $trial++) {
            [$roster, $reachable, $seats, $categories, $quota] = $this->randomCase();

            $positions = new PositionResolver($roster, $reachable, [], $seats);
            $qc = new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, $quota, $seats);
            $actual = $qc->result();
            // The closed forms (natural top, fixed-group alternation) and the
            // general DP must agree exactly; both are checked against the oracle.
            $viaDp = (new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, $quota, $seats, closedForms: false))->result();

            $outcomes = [];
            foreach ($this->linearExtensions($roster, $reachable) as $order) {
                $outcomes[] = $this->reference($order, $seats, $categories, $quota) + ['order' => $order];
            }
            $context = json_encode(compact('trial', 'roster', 'seats', 'categories', 'quota') + ['reach' => $this->pairs($reachable)], JSON_THROW_ON_ERROR);

            $slates = array_map(static fn (array $o): array => $o['slate'], $outcomes);
            $distinct = array_values(array_unique(array_map(static fn (array $s): string => implode(',', $s), $slates)));
            $allInfeasible = array_reduce($outcomes, static fn (bool $all, array $o): bool => $all && $o['infeasible'], true);
            $anyInfeasible = array_reduce($outcomes, static fn (bool $any, array $o): bool => $any || $o['infeasible'], false);

            $this->assertFalse($actual['too_complex'], "unexpected fail-safe: {$context}");
            $this->assertSame(count($distinct) > 1, $actual['provisional'], "provisional mismatch: {$context}");
            $this->assertSame($allInfeasible, $actual['infeasible'], "infeasible mismatch: {$context}");
            $this->assertSame($anyInfeasible && !$allInfeasible, $actual['partly_infeasible'], "partly-infeasible mismatch: {$context}");

            if (count($distinct) === 1) {
                $this->assertSame($slates[0], $actual['order'], "determined slate mismatch: {$context}");
            } else {
                $this->assertSame($this->commonPrefix($slates), $actual['order'], "common prefix mismatch: {$context}");
            }

            $intersection = array_values(array_intersect(...$slates));
            $union = array_values(array_unique(array_merge(...$slates)));
            $this->assertEqualsCanonicalizing($intersection, $actual['seated'], "seated mismatch: {$context}");
            $this->assertEqualsCanonicalizing(array_values(array_diff($union, $intersection)), $actual['contested'], "contested mismatch: {$context}");

            $this->assertSame($this->agreedPositions($slates), $this->stringKeyed($actual['positions']), "positions mismatch: {$context}");

            $this->assertSame($this->normalized($actual), $this->normalized($viaDp), "closed form vs DP mismatch: {$context}");

            $this->assertTiesExplainEveryResolution($qc->scenarios(), $actual, $outcomes, $context);
        }
    }

    /**
     * The tie explanation must be exact: its ties cover precisely the
     * undecided seats; in every resolution exactly one option of each tie
     * matches the resulting slate (and its infeasible flag); and an option's
     * condition, when it states one, holds in exactly the resolutions that
     * produce that option.
     *
     * @param list<array{seats:list<int>,options:list<array{when:list<array{ahead:string,behind:list<string>}>,seats:array<int,string>,out:list<string>,infeasible:bool}>}>|null $ties
     * @param array<string,mixed> $actual
     * @param list<array{order:list<string>,slate:list<string>,infeasible:bool}> $outcomes
     */
    private function assertTiesExplainEveryResolution(?array $ties, array $actual, array $outcomes, string $context): void
    {
        if (!$actual['provisional']) {
            $this->assertNull($ties, "final slate needs no tie explanation: {$context}");

            return;
        }
        if ($ties === null) {
            return; // too many outcomes to list -- allowed
        }

        $slateLength = count($outcomes[0]['slate']);
        /** @var array<array-key,int> $positions */
        $positions = $actual['positions'];
        $undecided = array_values(array_diff(range(1, max(1, $slateLength)), array_values($positions)));
        $covered = array_merge(...array_map(static fn (array $t): array => $t['seats'], $ties));
        sort($covered);
        $this->assertSame($undecided, $covered, "ties must cover exactly the undecided seats: {$context}");

        // Separate ties read as independent: every combination must occur.
        $distinctSlates = count(array_unique(array_map(static fn (array $o): string => implode("\0", $o['slate']), $outcomes)));
        $combinations = array_product(array_map(static fn (array $t): int => count($t['options']), $ties));
        $this->assertSame($distinctSlates, $combinations, "ties are not independent: {$context}");

        foreach ($outcomes as $outcome) {
            $at = array_flip($outcome['order']);
            foreach ($ties as $tie) {
                $matches = 0;
                foreach ($tie['options'] as $option) {
                    $hit = true;
                    foreach ($option['seats'] as $seat => $name) {
                        $hit = $hit && ($outcome['slate'][$seat - 1] ?? null) === (string) $name;
                    }
                    $matches += $hit ? 1 : 0;
                    if ($hit) {
                        $this->assertSame($outcome['infeasible'], $option['infeasible'], "option infeasible flag: {$context}");
                        foreach ($option['out'] as $out) {
                            $this->assertNotContains((string) $out, $outcome['slate'], "an 'out' candidate is seated: {$context}");
                        }
                    }
                    if ($option['when'] !== []) {
                        $holds = true;
                        foreach ($option['when'] as $w) {
                            foreach ($w['behind'] as $behind) {
                                $holds = $holds && $at[(string) $w['ahead']] < $at[(string) $behind];
                            }
                        }
                        $this->assertSame($hit, $holds, 'condition ' . json_encode($option['when']) . " not exact for order " . implode(',', $outcome['order']) . ": {$context}");
                    }
                }
                $this->assertSame(1, $matches, 'resolution ' . implode(',', $outcome['order']) . " matches {$matches} options of a tie: {$context}");
            }
        }
    }

    /**
     * Beyond the cap the answer must stay SOUND: nothing claimed seated
     * unless seated in every resolution, every candidate who could take a
     * seat (and is not reported seated) reported contested, no seat number
     * claimed. A tiny injected cap forces the fail-safe on most cases.
     */
    public function test_fail_safe_beyond_the_cap_is_sound(): void
    {
        mt_srand(20261005);
        $forced = 0;

        for ($trial = 0; $trial < 1500; $trial++) {
            [$roster, $reachable, $seats, $categories, $quota] = $this->randomCase();

            $positions = new PositionResolver($roster, $reachable, [], $seats);
            $actual = (new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, $quota, $seats, maxNodes: 3, closedForms: false))->result();
            if (!$actual['too_complex']) {
                continue;
            }
            $forced++;

            $slates = [];
            foreach ($this->linearExtensions($roster, $reachable) as $order) {
                $slates[] = $this->reference($order, $seats, $categories, $quota)['slate'];
            }
            $intersection = array_values(array_intersect(...$slates));
            $union = array_values(array_unique(array_merge(...$slates)));
            $context = json_encode(compact('trial', 'roster', 'seats', 'categories', 'quota') + ['reach' => $this->pairs($reachable)], JSON_THROW_ON_ERROR);

            $this->assertTrue($actual['provisional'], "fail-safe must be provisional: {$context}");
            $this->assertSame([], array_values(array_diff($actual['seated'], $intersection)), "claimed an uncertain seat: {$context}");
            $this->assertSame([], array_values(array_diff($union, $actual['seated'], $actual['contested'])), "omitted a possible seat-holder: {$context}");
            $this->assertSame([], array_values(array_intersect($actual['seated'], $actual['contested'])), "seated and contested overlap: {$context}");
            $this->assertSame([], $actual['positions'], "claimed a seat number: {$context}");
        }

        $this->assertGreaterThan(500, $forced);
    }

    /**
     * Review 2026-10-04 regression: 15 candidates all tied (two opposite
     * ballots), 5 seats, advisory min F 2 -- the leaf-set version ran out
     * of memory (fatal 500). Now exact and cheap: anyone may be seated,
     * nothing is certain.
     */
    public function test_fully_tied_field_is_exact_and_cheap(): void
    {
        $roster = array_map(static fn (int $i): string => "C{$i}", range(1, 15));
        $categories = [];
        foreach ($roster as $i => $c) {
            $categories[$c] = $i % 2 === 1 ? 'F' : 'M';
        }
        $matrix = new PairwiseMatrix([$roster, array_reverse($roster)], $roster);
        $schulze = new SchulzeBeatpath($matrix->decisivePairs(), $roster);
        $positions = new PositionResolver($roster, $schulze->reachable(), $matrix->matrix(), 5);

        memory_reset_peak_usage();
        $before = memory_get_usage();
        $result = (new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, ['category' => 'F', 'type' => 'min', 'count' => 2, 'binding' => false], 5))->result();

        $this->assertLessThan(16_000_000, memory_get_peak_usage() - $before);
        $this->assertFalse($result['too_complex']);
        $this->assertTrue($result['provisional']);
        $this->assertSame([], $result['seated']);
        $this->assertEqualsCanonicalizing($roster, $result['contested']);
        $this->assertSame([], $result['positions']);
    }

    /**
     * Review 2026-10-04 regression: sparse ballots (8 voters ranking 10 of
     * 40, 20 seats) left order ties among surely-elected candidates that
     * pushed the leaf-set version past its cap, so the page showed nobody
     * elected. Every quota type must now resolve exactly, quickly.
     */
    public function test_sparse_ballots_on_a_large_roster_resolve_without_the_fail_safe(): void
    {
        mt_srand(3);
        $roster = array_map(static fn (int $i): string => "C{$i}", range(1, 40));
        $categories = [];
        foreach ($roster as $i => $c) {
            $categories[$c] = $i % 2 === 1 ? 'F' : 'M';
        }
        $ballots = [];
        for ($v = 0; $v < 8; $v++) {
            $shuffled = $roster;
            shuffle($shuffled);
            $ballots[] = array_slice($shuffled, 0, 10);
        }
        $matrix = new PairwiseMatrix($ballots, $roster);
        $schulze = new SchulzeBeatpath($matrix->decisivePairs(), $roster);
        $positions = new PositionResolver($roster, $schulze->reachable(), $matrix->matrix(), 20);

        foreach ([
            ['category' => 'F', 'type' => 'min', 'count' => 10, 'binding' => true],
            ['category' => 'F', 'type' => 'max', 'count' => 6, 'binding' => true],
            ['category' => '', 'type' => 'alternate', 'count' => 0, 'binding' => true],
        ] as $quota) {
            $start = microtime(true);
            $result = (new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, $quota, 20))->result();

            $this->assertFalse($result['too_complex'], $quota['type']);
            $this->assertLessThan(2.0, microtime(true) - $start, $quota['type']);
            $this->assertNotSame([], $result['seated'], $quota['type']);
            $this->assertSame(20, count($result['seated']) + min(count($result['contested']), 20 - count($result['seated'])), $quota['type']);
        }
    }

    /**
     * Symmetry reduction keeps a large fully-tied tail tractable: 2 elected
     * M candidates, then 15 candidates all tied (10 M, 5 F), 2 seats, min F
     * 1. B is surely demoted; any of the 5 F may be promoted. 15! linear
     * extensions -- must resolve exactly, not hit the safety cap.
     */
    public function test_large_fully_tied_tail_is_resolved_exactly(): void
    {
        $roster = ['A', 'B'];
        $categories = ['A' => 'M', 'B' => 'M'];
        for ($i = 1; $i <= 10; $i++) {
            $roster[] = "M{$i}";
            $categories["M{$i}"] = 'M';
        }
        for ($i = 1; $i <= 5; $i++) {
            $roster[] = "F{$i}";
            $categories["F{$i}"] = 'F';
        }
        $reachable = [];
        foreach ($roster as $x) {
            foreach ($roster as $y) {
                $reachable[$x][$y] = $x !== $y && ($x === 'A' || ($x === 'B' && $y !== 'A'));
            }
        }

        $positions = new PositionResolver($roster, $reachable, [], 2);
        $qc = new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, ['category' => 'F', 'type' => 'min', 'count' => 1, 'binding' => true], 2);
        $result = $qc->result();

        $this->assertSame(['A'], $result['seated']);
        $this->assertSame(['F1', 'F2', 'F3', 'F4', 'F5'], $result['contested']);
        $this->assertSame(['A' => 1], $result['positions']);
        $this->assertTrue($result['provisional']);
        $this->assertFalse($result['too_complex']);
    }

    /**
     * A tie at #1 between two candidates of the SAME group still fixes the
     * start group: F1/F2 tied on top, then M1 > M2 > F3, alternation over
     * 3 seats. Both resolutions zip F, M, F, so M1 is certain at seat 2 and
     * F1/F2 are seated but their seats hinge on the tie.
     */
    public function test_tie_for_first_within_one_group_keeps_the_start_group(): void
    {
        $roster = ['F1', 'F2', 'M1', 'M2', 'F3'];
        $categories = ['F1' => 'F', 'F2' => 'F', 'M1' => 'M', 'M2' => 'M', 'F3' => 'F'];
        $rank = ['F1' => 0, 'F2' => 0, 'M1' => 1, 'M2' => 2, 'F3' => 3];
        $reachable = [];
        foreach ($roster as $x) {
            foreach ($roster as $y) {
                $reachable[$x][$y] = $rank[$x] < $rank[$y];
            }
        }

        $positions = new PositionResolver($roster, $reachable, [], 3);
        $quota = ['category' => '', 'type' => 'alternate', 'count' => 0, 'binding' => true];
        $result = (new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, $quota, 3))->result();
        $viaDp = (new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, $quota, 3, closedForms: false))->result();

        $this->assertEqualsCanonicalizing(['F1', 'F2', 'M1'], $result['seated']);
        $this->assertSame([], $result['contested']);
        $this->assertSame(['M1' => 2], $result['positions']);
        $this->assertTrue($result['provisional']);
        $this->assertFalse($result['infeasible']);
        $this->assertFalse($result['too_complex']);
        $this->assertSame($this->normalized($result), $this->normalized($viaDp));
    }

    /**
     * The tie explanation names only the tie that matters: M1 > M2, then
     * M3, F1, F2 all tied; 3 seats, min 1 F. Whether M3 is third does not
     * matter (an F enters either way) -- only F1 vs F2 decides seat 3.
     * A final slate (2 seats, min 1 M: M1, M2) has nothing to explain.
     */
    public function test_tie_explanation_names_only_the_deciding_tie(): void
    {
        $roster = ['M1', 'M2', 'M3', 'F1', 'F2'];
        $categories = ['M1' => 'M', 'M2' => 'M', 'M3' => 'M', 'F1' => 'F', 'F2' => 'F'];
        $rank = ['M1' => 0, 'M2' => 1, 'M3' => 2, 'F1' => 2, 'F2' => 2];
        $reachable = [];
        foreach ($roster as $x) {
            foreach ($roster as $y) {
                $reachable[$x][$y] = $rank[$x] < $rank[$y];
            }
        }
        $positions = new PositionResolver($roster, $reachable, [], 3);
        $quota = ['category' => 'F', 'type' => 'min', 'count' => 1, 'binding' => true];

        $ties = (new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, $quota, 3))->scenarios();

        $this->assertSame([[
            'seats' => [3],
            'options' => [
                ['when' => [['ahead' => 'F1', 'behind' => ['F2']]], 'seats' => [3 => 'F1'], 'out' => ['F2'], 'infeasible' => false],
                ['when' => [['ahead' => 'F2', 'behind' => ['F1']]], 'seats' => [3 => 'F2'], 'out' => ['F1'], 'infeasible' => false],
            ],
        ]], $ties);

        $final = new PositionResolver($roster, $reachable, [], 2);
        $minM = ['category' => 'M', 'type' => 'min', 'count' => 1, 'binding' => true];
        $this->assertNull((new QuotaCorrector($final->ranking(), $final->bands(), $categories, $minM, 2))->scenarios());
    }

    /**
     * An alternation that no tie resolution can apply is infeasible even
     * when the start group hinges on a tie, and the fail-safe says so too:
     * A (F) and B (untagged) tied, 1 seat -- an untagged #1 has no start
     * group, and F alone has no second group.
     */
    public function test_alternation_infeasible_in_every_resolution_is_reported_infeasible(): void
    {
        $roster = ['A', 'B'];
        $reachable = ['A' => ['A' => false, 'B' => false], 'B' => ['A' => false, 'B' => false]];
        $positions = new PositionResolver($roster, $reachable, [], 1);
        $quota = ['category' => '', 'type' => 'alternate', 'count' => 0, 'binding' => true];

        foreach ([[QuotaCorrector::MAX_NODES, true], [0, false]] as [$maxNodes, $closedForms]) {
            $result = (new QuotaCorrector($positions->ranking(), $positions->bands(), ['A' => 'F'], $quota, 1, $maxNodes, $closedForms))->result();

            $this->assertTrue($result['infeasible']);
            $this->assertSame([], $result['seated']);
            $this->assertEqualsCanonicalizing(['A', 'B'], $result['contested']);
        }
    }

    /**
     * A stale category label for someone off the roster must not make an
     * absent quota category look present.
     */
    public function test_category_only_off_the_roster_counts_as_absent(): void
    {
        $roster = ['A', 'B'];
        $reachable = ['A' => ['A' => false, 'B' => true], 'B' => ['A' => false, 'B' => false]];
        $positions = new PositionResolver($roster, $reachable, [], 1);
        $qc = new QuotaCorrector($positions->ranking(), $positions->bands(), ['A' => 'M', 'B' => 'M', 'Z' => 'F'], ['category' => 'F', 'type' => 'min', 'count' => 1, 'binding' => true], 1);

        $this->assertFalse($qc->result()['infeasible']);
        $this->assertSame([__('components.orderedlist.quota_warn_category_absent', ['category' => 'F'])], $qc->warnings());
    }

    /**
     * Below the cut a min quota only cares WHETHER a candidate can enter,
     * not its exact category: 13 tied candidates in 13 different
     * categories, only S is Sales, 1 seat, min Sales 1. Every resolution
     * gives [S]; 13! orders, but the walk must still resolve it exactly.
     */
    public function test_tied_tail_of_distinct_categories_is_resolved_exactly(): void
    {
        $roster = ['S'];
        $categories = ['S' => 'Sales'];
        for ($i = 1; $i <= 12; $i++) {
            $roster[] = "X{$i}";
            $categories["X{$i}"] = "K{$i}";
        }
        $reachable = [];
        foreach ($roster as $x) {
            foreach ($roster as $y) {
                $reachable[$x][$y] = false;
            }
        }

        $positions = new PositionResolver($roster, $reachable, [], 1);
        $qc = new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true], 1);
        $result = $qc->result();

        $this->assertSame(['S'], $result['order']);
        $this->assertFalse($result['provisional']);
        $this->assertSame(['S' => 1], $result['positions']);
    }

    /**
     * Beyond the safety cap the result fails SAFE: provisional, nothing
     * certain, a warning -- never a guess. (Cap injected small so the
     * scenario stays tiny.)
     */
    public function test_tree_beyond_the_safety_cap_fails_safe(): void
    {
        $roster = ['F1', 'F2', 'M1', 'M2'];
        $categories = ['F1' => 'F', 'F2' => 'F', 'M1' => 'M', 'M2' => 'M'];
        $reachable = [];
        foreach ($roster as $x) {
            foreach ($roster as $y) {
                $reachable[$x][$y] = false;
            }
        }

        $positions = new PositionResolver($roster, $reachable, [], 2);
        $qc = new QuotaCorrector($positions->ranking(), $positions->bands(), $categories, ['category' => '', 'type' => 'alternate', 'count' => 0, 'binding' => true], 2, maxNodes: 2);
        $result = $qc->result();

        $this->assertTrue($result['provisional']);
        $this->assertTrue($result['too_complex']);
        $this->assertFalse($result['infeasible']);
        $this->assertSame([], $result['order']);
        $this->assertSame([], $result['seated']);
        $this->assertSame($roster, $result['contested']);
        $this->assertSame([], $result['positions']);
        $this->assertContains(__('components.orderedlist.quota_warn_too_complex'), $qc->warnings());
    }

    // --- independent oracle ---------------------------------------------

    /**
     * Plain restatement of the quota rules on ONE total order.
     *
     * @param list<string> $order
     * @param array<string,string> $categories
     * @param array{category:string,type:string,count:int,binding:bool} $quota
     * @return array{slate:list<string>,infeasible:bool}
     */
    private function reference(array $order, int $seats, array $categories, array $quota): array
    {
        $seats = min($seats, count($order));
        $top = array_slice($order, 0, $seats);
        $below = array_slice($order, $seats);
        $cat = static fn (string $c): ?string => $categories[$c] ?? null;
        $natural = ['slate' => $top, 'infeasible' => false];
        $infeasible = ['slate' => $top, 'infeasible' => true];

        if ($quota['type'] === 'alternate') {
            $g1 = $cat($order[0]);
            if ($g1 === null) {
                return $infeasible;
            }
            $topCats = [];
            foreach ($top as $c) {
                if ($cat($c) === null) {
                    return $infeasible;
                }
                $topCats[$cat($c)] = true;
            }
            if (count($topCats) > 2) {
                return $infeasible;
            }
            if (count($topCats) === 2) {
                $g2 = array_values(array_diff(array_keys($topCats), [$g1]))[0];
            } else {
                $others = array_values(array_unique(array_filter(array_map($cat, $order), static fn (?string $k): bool => $k !== null && $k !== $g1)));
                if (count($others) !== 1) {
                    return $infeasible;
                }
                $g2 = $others[0];
            }
            $p1 = array_values(array_filter($order, static fn (string $c): bool => $cat($c) === $g1));
            $p2 = array_values(array_filter($order, static fn (string $c): bool => $cat($c) === $g2));
            if (count($p1) + count($p2) < $seats) {
                return $infeasible;
            }
            $slate = [];
            for ($i = 0; $i < $seats; $i++) {
                $want = $i % 2 === 0 ? 1 : 2;
                if ($want === 1 && $p1 === []) {
                    $want = 2;
                } elseif ($want === 2 && $p2 === []) {
                    $want = 1;
                }
                $pick = $want === 1 ? array_shift($p1) : array_shift($p2);
                assert($pick !== null); // the pool-size check above guarantees a pick
                $slate[] = $pick;
            }

            return ['slate' => $slate, 'infeasible' => false];
        }

        $k = $quota['category'];
        if (!in_array($k, $categories, true)) {
            return $natural;
        }
        $isMin = $quota['type'] === 'min';
        $inCut = count(array_filter($top, static fn (string $c): bool => $cat($c) === $k));
        $need = $isMin ? $quota['count'] - $inCut : $inCut - $quota['count'];
        if ($need <= 0) {
            return $natural;
        }
        $leavers = array_values(array_filter($top, static fn (string $c): bool => ($cat($c) === $k) !== $isMin));
        $enterers = array_values(array_filter($below, static fn (string $c): bool => ($cat($c) === $k) === $isMin));
        if (count($leavers) < $need || count($enterers) < $need) {
            return $infeasible;
        }
        $leaving = array_slice($leavers, -$need);

        return [
            'slate' => [...array_values(array_diff($top, $leaving)), ...array_slice($enterers, 0, $need)],
            'infeasible' => false,
        ];
    }

    /**
     * @param list<string> $roster
     * @param array<string,array<string,bool>> $reachable
     * @return list<list<string>>
     */
    private function linearExtensions(array $roster, array $reachable): array
    {
        if ($roster === []) {
            return [[]];
        }
        $out = [];
        foreach ($roster as $c) {
            foreach ($roster as $other) {
                if ($reachable[$other][$c] ?? false) {
                    continue 2;
                }
            }
            foreach ($this->linearExtensions(array_values(array_diff($roster, [$c])), $reachable) as $tail) {
                $out[] = [$c, ...$tail];
            }
        }

        return $out;
    }

    /**
     * One random scenario: roster (sometimes numeric labels), partial
     * order, seats, categories (some untagged), quota (some advisory).
     *
     * @return array{0:list<string>,1:array<string,array<string,bool>>,2:int,3:array<string,string>,4:array{category:string,type:string,count:int,binding:bool}}
     */
    private function randomCase(): array
    {
        $n = mt_rand(1, 7);
        $numeric = mt_rand(0, 5) === 0;
        $roster = array_map(static fn (int $i): string => $numeric ? (string) ($i - 1) : "c{$i}", range(1, $n));
        $seats = mt_rand(1, $n);

        return [$roster, $this->randomPartialOrder($roster), $seats, $this->randomCategories($roster), $this->randomQuota($seats)];
    }

    /**
     * A result with order-insensitive lists sorted, for comparing two
     * correctors.
     *
     * @param array<string,mixed> $result
     * @return array<string,mixed>
     */
    private function normalized(array $result): array
    {
        foreach (['seated', 'contested'] as $list) {
            /** @var list<string> $names */
            $names = $result[$list];
            sort($names, SORT_STRING);
            $result[$list] = $names;
        }
        /** @var array<array-key,int> $positions */
        $positions = $result['positions'];
        $result['positions'] = $this->stringKeyed($positions);

        return $result;
    }

    /**
     * Seat numbers identical in every slate, keyed by candidate (string).
     *
     * @param list<list<string>> $slates
     * @return array<string,int>
     */
    private function agreedPositions(array $slates): array
    {
        $agreed = [];
        foreach ($slates[0] as $i => $c) {
            if (array_reduce($slates, static fn (bool $all, array $s): bool => $all && ($s[$i] ?? null) === $c, true)) {
                $agreed[$c] = $i + 1;
            }
        }

        return $this->stringKeyed($agreed);
    }

    /**
     * @param array<array-key,int> $positions
     * @return array<string,int>
     */
    private function stringKeyed(array $positions): array
    {
        $out = [];
        foreach ($positions as $c => $p) {
            $out[(string) $c] = $p;
        }
        ksort($out, SORT_STRING);

        return $out;
    }

    /**
     * Random strict partial order: random DAG over a hidden permutation,
     * transitively closed.
     *
     * @param list<string> $roster
     * @return array<string,array<string,bool>>
     */
    private function randomPartialOrder(array $roster): array
    {
        $perm = $roster;
        shuffle($perm);
        $density = [0.0, 0.3, 0.6, 0.85, 1.0][mt_rand(0, 4)];
        $reach = [];
        foreach ($roster as $x) {
            foreach ($roster as $y) {
                $reach[$x][$y] = false;
            }
        }
        foreach ($perm as $i => $x) {
            foreach (array_slice($perm, $i + 1) as $y) {
                if (mt_rand() / mt_getrandmax() < $density) {
                    $reach[$x][$y] = true;
                }
            }
        }
        foreach ($roster as $k) {
            foreach ($roster as $i) {
                foreach ($roster as $j) {
                    if ($reach[$i][$k] && $reach[$k][$j]) {
                        $reach[$i][$j] = true;
                    }
                }
            }
        }

        return $reach;
    }

    /**
     * @param list<string> $roster
     * @return array<string,string>
     */
    private function randomCategories(array $roster): array
    {
        $palette = [
            ['F', 'M'],
            ['F', 'M', 'F', 'M', null],
            ['F', 'M', 'G', null],
            ['F', 'M', 'G', 'H'],
            ['F'],
            ['F', null, null],
        ][mt_rand(0, 5)];
        $categories = [];
        foreach ($roster as $c) {
            $k = $palette[mt_rand(0, count($palette) - 1)];
            if ($k !== null) {
                $categories[$c] = $k;
            }
        }

        return $categories;
    }

    /**
     * @return array{category:string,type:string,count:int,binding:bool}
     */
    private function randomQuota(int $seats): array
    {
        $type = ['min', 'max', 'alternate', 'alternate'][mt_rand(0, 3)];
        if ($type === 'alternate') {
            return ['category' => '', 'type' => 'alternate', 'count' => 0, 'binding' => mt_rand(0, 3) > 0];
        }

        return ['category' => ['F', 'M', 'G', 'Z'][mt_rand(0, 3)], 'type' => $type, 'count' => mt_rand($type === 'min' ? 1 : 0, $seats + 1), 'binding' => mt_rand(0, 3) > 0];
    }

    /**
     * @param list<list<string>> $slates
     * @return list<string>
     */
    private function commonPrefix(array $slates): array
    {
        $prefix = [];
        foreach ($slates[0] as $i => $c) {
            foreach ($slates as $s) {
                if (($s[$i] ?? null) !== $c) {
                    return $prefix;
                }
            }
            $prefix[] = $c;
        }

        return $prefix;
    }

    /**
     * @param array<string,array<string,bool>> $reachable
     * @return list<string>
     */
    private function pairs(array $reachable): array
    {
        $pairs = [];
        foreach ($reachable as $x => $row) {
            foreach ($row as $y => $r) {
                if ($r) {
                    $pairs[] = "{$x}>{$y}";
                }
            }
        }

        return $pairs;
    }
}
