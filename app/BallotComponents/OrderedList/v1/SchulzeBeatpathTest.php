<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use Tests\TestCase;

/**
 * Pins the Schulze beatpath (margins) resolver against HAND-COMPUTED
 * matrices -- never against the code's own output -- per spec §3/§5.
 */
class SchulzeBeatpathTest extends TestCase
{
    /**
     * @param list<array{winner:string,loser:string,margin:int,for?:int,against?:int}> $pairs
     * @return list<array{winner:string,loser:string,margin:int,for:int,against:int}>
     */
    private function pairs(array $pairs): array
    {
        return array_map(static function (array $p): array {
            return [
                'winner' => $p['winner'],
                'loser' => $p['loser'],
                'margin' => $p['margin'],
                'for' => $p['for'] ?? $p['margin'],
                'against' => $p['against'] ?? 0,
            ];
        }, $pairs);
    }

    // --- (a) Condorcet winner respected --------------------------------

    /**
     * A beats both B and C decisively, B beats C decisively -- a clean,
     * acyclic chain. A is the Condorcet winner and must rank strictly first;
     * every relation is decided by a direct edge (no widest-path detour ever
     * beats a stronger direct edge here).
     *
     * Hand-computed: p[A][B]=5, p[A][C]=max(3, min(5,2))=max(3,2)=3 (direct
     * wins), p[B][C]=2; all three reverse cells stay null (no path).
     */
    public function test_condorcet_winner_is_respected(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 5],
            ['winner' => 'A', 'loser' => 'C', 'margin' => 3],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 2],
        ]);
        $s = new SchulzeBeatpath($decisive, ['A', 'B', 'C']);

        $strength = $s->strength();
        $this->assertSame(5, $strength['A']['B']);
        $this->assertSame(3, $strength['A']['C']);
        $this->assertSame(2, $strength['B']['C']);
        $this->assertNull($strength['B']['A']);
        $this->assertNull($strength['C']['A']);
        $this->assertNull($strength['C']['B']);

        $reachable = $s->reachable();
        $this->assertTrue($reachable['A']['B']);
        $this->assertTrue($reachable['A']['C']);
        $this->assertTrue($reachable['B']['C']);
        $this->assertFalse($reachable['B']['A']);
        $this->assertFalse($reachable['C']['A']);
        $this->assertFalse($reachable['C']['B']);
    }

    // --- (b) 3-way decisive cycle resolved by beatpath -------------------

    /**
     * A decisive Condorcet cycle with strictly decreasing margins around the
     * loop: A>B (5), B>C (3), C>A (1). Hand-computed widest paths:
     *   p[A][C] = min(p[A][B]=5, p[B][C]=3) = 3   (via B)
     *   p[B][A] = min(p[B][C]=3, p[C][A]=1) = 1   (via C)
     *   p[C][B] = min(p[C][A]=1, p[A][B]=5) = 1   (via A)
     * Final: A beats B (5>1), B beats C (3>1), A beats C (3>1) -> strict
     * order A > B > C, with no genuine tie anywhere.
     */
    public function test_three_way_cycle_is_resolved_to_the_hand_computed_strict_order(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 5],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 3],
            ['winner' => 'C', 'loser' => 'A', 'margin' => 1],
        ]);
        $s = new SchulzeBeatpath($decisive, ['A', 'B', 'C']);

        $strength = $s->strength();
        $this->assertSame(5, $strength['A']['B']);
        $this->assertSame(3, $strength['B']['C']);
        $this->assertSame(1, $strength['C']['A']);
        $this->assertSame(3, $strength['A']['C']);
        $this->assertSame(1, $strength['B']['A']);
        $this->assertSame(1, $strength['C']['B']);

        $reachable = $s->reachable();
        $this->assertTrue($reachable['A']['B']);
        $this->assertTrue($reachable['B']['C']);
        $this->assertTrue($reachable['A']['C']);
        $this->assertFalse($reachable['B']['A']);
        $this->assertFalse($reachable['C']['B']);
        $this->assertFalse($reachable['C']['A']);

        $winners = $s->winners();
        $this->assertSame(
            [
                ['winner' => 'A', 'loser' => 'B', 'strength' => 5, 'path' => ['A', 'B']],
                ['winner' => 'A', 'loser' => 'C', 'strength' => 3, 'path' => ['A', 'B', 'C']],
                ['winner' => 'B', 'loser' => 'C', 'strength' => 3, 'path' => ['B', 'C']],
            ],
            $winners
        );
    }

    // --- (c) genuine symmetric tie -> surfaced, never broken --------------

    /**
     * A perfectly symmetric 3-voter Condorcet cycle (A>B>C, B>C>A, C>A>B):
     * every pairwise margin is decisive but identical (1). Hand-computed:
     *   p[A][B]=1, p[B][C]=1, p[C][A]=1 (seeded)
     *   p[A][C] = min(1,1) = 1 (via B); p[B][A] = min(1,1) = 1 (via C);
     *   p[C][B] = min(1,1) = 1 (via A)
     * Every pair ties exactly (1 vs 1) in both directions -- a genuine tie
     * that must be surfaced, not broken by any arbitrary rule.
     */
    public function test_symmetric_cycle_is_a_genuine_tie_surfaced_not_broken(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 1],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 1],
            ['winner' => 'C', 'loser' => 'A', 'margin' => 1],
        ]);
        $s = new SchulzeBeatpath($decisive, ['A', 'B', 'C']);

        $strength = $s->strength();
        $this->assertSame(1, $strength['A']['B']);
        $this->assertSame(1, $strength['B']['A']);
        $this->assertSame(1, $strength['B']['C']);
        $this->assertSame(1, $strength['C']['B']);
        $this->assertSame(1, $strength['C']['A']);
        $this->assertSame(1, $strength['A']['C']);

        $reachable = $s->reachable();
        foreach ($reachable as $row) {
            foreach ($row as $value) {
                $this->assertFalse($value);
            }
        }

        $this->assertSame([], $s->winners());
    }

    // --- (d) known worked example vs. a hand-computed beatpath matrix -----

    /**
     * A 4-candidate decisive cycle A->B->C->D->A with strictly decreasing
     * margins (10, 7, 4, 2) around the loop -- the flagship Schulze example:
     * the DIRECT comparison has D beating A (margin 2), yet A's INDIRECT
     * beatpath to D (via B, C) is strength 4, stronger than D's direct path
     * back to A -- so the beatpath relation overturns the raw pairwise
     * result. Hand-computed (enumerating all 12 ordered pairs; the graph has
     * exactly one simple path each way around the 4-cycle, so no other route
     * competes):
     *   Forward (with the cycle):   p[A][B]=10, p[B][C]=7, p[C][D]=4, p[D][A]=2
     *   p[A][C]=min(10,7)=7 (via B); p[A][D]=min(7,4)=4 (via B,C)
     *   p[B][D]=min(7,4)=4 (via C)
     *   Backward (against the cycle): p[B][A]=min(p[B][D]=4,p[D][A]=2)=2 (via D)
     *   p[C][A]=min(p[C][D]=4,p[D][A]=2)=2 (via D); p[C][B]=min(p[C][A]=2,p[A][B]=10)=2 (via A)
     *   p[D][B]=min(p[D][A]=2,p[A][B]=10)=2 (via A); p[D][C]=min(p[D][B]=2,p[B][C]=7)=2 (via A,B)
     * Final: A beats B,C,D; B beats C,D; C beats D; D beats nobody --
     * strict order A > B > C > D, including A > D despite D's direct win.
     */
    public function test_known_worked_example_matches_the_hand_computed_beatpath_matrix(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 10],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 7],
            ['winner' => 'C', 'loser' => 'D', 'margin' => 4],
            ['winner' => 'D', 'loser' => 'A', 'margin' => 2],
        ]);
        $s = new SchulzeBeatpath($decisive, ['A', 'B', 'C', 'D']);

        $strength = $s->strength();
        $this->assertSame(10, $strength['A']['B']);
        $this->assertSame(7, $strength['A']['C']);
        $this->assertSame(4, $strength['A']['D']);
        $this->assertSame(2, $strength['B']['A']);
        $this->assertSame(7, $strength['B']['C']);
        $this->assertSame(4, $strength['B']['D']);
        $this->assertSame(2, $strength['C']['A']);
        $this->assertSame(2, $strength['C']['B']);
        $this->assertSame(4, $strength['C']['D']);
        $this->assertSame(2, $strength['D']['A']);
        $this->assertSame(2, $strength['D']['B']);
        $this->assertSame(2, $strength['D']['C']);

        $reachable = $s->reachable();
        $this->assertTrue($reachable['A']['B']);
        $this->assertTrue($reachable['A']['C']);
        // The headline assertion: beatpath overturns the direct D>A result.
        $this->assertTrue($reachable['A']['D']);
        $this->assertFalse($reachable['D']['A']);
        $this->assertTrue($reachable['B']['C']);
        $this->assertTrue($reachable['B']['D']);
        $this->assertTrue($reachable['C']['D']);
        $this->assertFalse($reachable['D']['B']);
        $this->assertFalse($reachable['D']['C']);
        $this->assertFalse($reachable['C']['B']);
        $this->assertFalse($reachable['C']['A']);
        $this->assertFalse($reachable['B']['A']);

        $winners = $s->winners();
        $byPair = [];
        foreach ($winners as $w) {
            $byPair[$w['winner'] . '>' . $w['loser']] = $w;
        }
        $this->assertSame(['A', 'B', 'C', 'D'], $byPair['A>D']['path']);
        $this->assertSame(4, $byPair['A>D']['strength']);
        $this->assertSame(['A', 'B'], $byPair['A>B']['path']);
        $this->assertCount(6, $winners);
    }

    // --- (e) agreement with the old Ranked-Pairs result on a non-cyclic
    //         fixture ------------------------------------------------------

    /**
     * On an ACYCLIC decisive-pairs graph, Schulze and Ranked Pairs always
     * agree: with no cycle, every decisive edge is (mathematically) safe to
     * lock, so Ranked Pairs' reachable() is exactly the transitive closure
     * of the decisive-pairs DAG -- and so is Schulze's, because with no
     * path back in the reverse direction (p[loser][winner] stays null for
     * every pair reachable only forward), `beats()` reduces to "does ANY
     * forward path exist", i.e. the same transitive closure. This fixture
     * is the acyclic "clean chain" A>B(3), B>C(2), A>C(4) that
     * RankedPairsLockTest (now removed) pinned as `test_clean_chain_locks_all`
     * -- the expected reachable() shape here is byte-identical to that old
     * RP test's assertions.
     */
    public function test_agrees_with_the_old_ranked_pairs_result_on_a_non_cyclic_fixture(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 3],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 2],
            ['winner' => 'A', 'loser' => 'C', 'margin' => 4],
        ]);
        $s = new SchulzeBeatpath($decisive, ['A', 'B', 'C']);

        $reachable = $s->reachable();
        $this->assertTrue($reachable['A']['B']);
        $this->assertTrue($reachable['A']['C']);
        $this->assertTrue($reachable['B']['C']);
        $this->assertFalse($reachable['C']['A']);
        $this->assertFalse($reachable['C']['B']);
        $this->assertFalse($reachable['B']['A']);

        // The direct A>C edge (margin 4) already dominates the via-B detour
        // (min(3,2)=2), so the direct edge stands unmodified.
        $this->assertSame(4, $s->strength()['A']['C']);
    }

    // --- edge cases --------------------------------------------------------

    public function test_single_candidate_has_no_pairs_to_resolve(): void
    {
        $s = new SchulzeBeatpath([], ['Solo']);

        $this->assertSame([], $s->winners());
        $this->assertSame(['Solo' => ['Solo' => false]], $s->reachable());
    }

    public function test_no_decisive_pairs_at_all_is_all_tied(): void
    {
        $s = new SchulzeBeatpath([], ['A', 'B', 'C']);

        foreach ($s->reachable() as $row) {
            foreach ($row as $value) {
                $this->assertFalse($value);
            }
        }
        $this->assertSame([], $s->winners());
    }

    // --- (f) reachable() invariants: property-based, deterministic seed ---

    /**
     * The class docblock claims `reachable()` needs no further transitive-
     * closure step because Schulze's method is PROVEN to always yield a
     * transitive (and, by construction of `beats()`, asymmetric) ranking.
     * This property test exercises that claim against the implementation
     * directly, rather than trusting the docblock: over >=500 random
     * decisive-pair edge sets (4-6 candidates, random positive margins, a
     * fixed PRNG seed for reproducibility), `reachable()` must always be
     * BOTH asymmetric (never both `reachable(a,b)` and `reachable(b,a)`)
     * AND transitive (`reachable(a,b) && reachable(b,c)` implies
     * `reachable(a,c)`) -- the invariant PositionResolver depends on.
     */
    public function test_reachable_is_asymmetric_and_transitive_over_many_random_edge_sets(): void
    {
        mt_srand(20260927);

        $allCandidates = ['A', 'B', 'C', 'D', 'E', 'F'];

        for ($trial = 0; $trial < 500; $trial++) {
            $n = mt_rand(4, 6);
            /** @var list<string> $roster */
            $roster = array_slice($allCandidates, 0, $n);

            /** @var list<array{winner:string,loser:string,margin:int,for:int,against:int}> $decisive */
            $decisive = [];
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    // ~70% chance this unordered pair gets a decisive edge at all.
                    if (mt_rand(1, 100) > 70) {
                        continue;
                    }
                    $margin = mt_rand(1, 100);
                    if (mt_rand(0, 1) === 0) {
                        $decisive[] = ['winner' => $roster[$i], 'loser' => $roster[$j], 'margin' => $margin, 'for' => $margin, 'against' => 0];
                    } else {
                        $decisive[] = ['winner' => $roster[$j], 'loser' => $roster[$i], 'margin' => $margin, 'for' => $margin, 'against' => 0];
                    }
                }
            }

            $s = new SchulzeBeatpath($decisive, $roster);
            $reachable = $s->reachable();

            foreach ($roster as $a) {
                foreach ($roster as $b) {
                    if ($a === $b) {
                        continue;
                    }

                    $this->assertFalse(
                        $reachable[$a][$b] && $reachable[$b][$a],
                        "trial {$trial}: reachable({$a},{$b}) and reachable({$b},{$a}) both true"
                    );

                    foreach ($roster as $cc) {
                        if ($cc === $a || $cc === $b) {
                            continue;
                        }
                        if ($reachable[$a][$b] && $reachable[$b][$cc]) {
                            $this->assertTrue(
                                $reachable[$a][$cc],
                                "trial {$trial}: reachable({$a},{$b}) && reachable({$b},{$cc}) but not reachable({$a},{$cc})"
                            );
                        }
                    }
                }
            }
        }
    }
}
