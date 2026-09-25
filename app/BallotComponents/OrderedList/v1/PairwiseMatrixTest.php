<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use Tests\TestCase;

class PairwiseMatrixTest extends TestCase
{
    public function test_basic_preferences_and_decisive_pairs(): void
    {
        $roster = ['A', 'B', 'C'];
        $ballots = [['A', 'B'], ['A', 'B'], ['A']];
        $pm = new PairwiseMatrix($ballots, $roster);

        $this->assertSame(3, $pm->prefers('A', 'B'));
        $this->assertSame(0, $pm->prefers('B', 'A'));
        $this->assertSame(3, $pm->prefers('A', 'C'));
        $this->assertSame(2, $pm->prefers('B', 'C'));
        $this->assertSame(0, $pm->prefers('C', 'B'));

        $decisive = $pm->decisivePairs();
        $this->assertCount(3, $decisive);

        $byPair = [];
        foreach ($decisive as $edge) {
            $byPair[$edge['winner'] . '-' . $edge['loser']] = $edge;
        }
        $this->assertArrayHasKey('A-C', $byPair);
        $this->assertSame(3, $byPair['A-C']['margin']);
        $this->assertArrayHasKey('B-C', $byPair);
        $this->assertSame(2, $byPair['B-C']['margin']);
        $this->assertArrayHasKey('A-B', $byPair);
    }

    public function test_tie_pair_is_not_decisive(): void
    {
        $roster = ['A', 'B'];
        $ballots = [['A'], ['B']];
        $pm = new PairwiseMatrix($ballots, $roster);

        $this->assertSame(1, $pm->prefers('A', 'B'));
        $this->assertSame(1, $pm->prefers('B', 'A'));
        $this->assertSame([], $pm->decisivePairs());
    }

    public function test_both_unapproved_is_no_preference(): void
    {
        $roster = ['A', 'B', 'C'];
        $ballots = [['A']];
        $pm = new PairwiseMatrix($ballots, $roster);

        $this->assertSame(0, $pm->prefers('B', 'C'));
        $this->assertSame(0, $pm->prefers('C', 'B'));
    }

    public function test_candidates_returns_roster_as_given(): void
    {
        $roster = ['C', 'A', 'B'];
        $pm = new PairwiseMatrix([], $roster);

        $this->assertSame(['C', 'A', 'B'], $pm->candidates());
    }

    public function test_matrix_has_every_ordered_pair_zero_default(): void
    {
        $roster = ['A', 'B', 'C'];
        $pm = new PairwiseMatrix([], $roster);

        $matrix = $pm->matrix();
        foreach ($roster as $x) {
            foreach ($roster as $y) {
                if ($x === $y) {
                    continue;
                }
                $this->assertSame(0, $matrix[$x][$y]);
            }
        }
    }

    public function test_decisive_pairs_winner_is_larger_side_with_for_against(): void
    {
        $roster = ['A', 'B'];
        // 3 ballots prefer A over B (A ranked, B not), 1 prefers B over A.
        $ballots = [['A'], ['A'], ['A'], ['B']];
        $pm = new PairwiseMatrix($ballots, $roster);

        $decisive = $pm->decisivePairs();
        $this->assertCount(1, $decisive);
        $this->assertSame('A', $decisive[0]['winner']);
        $this->assertSame('B', $decisive[0]['loser']);
        $this->assertSame(3, $decisive[0]['for']);
        $this->assertSame(1, $decisive[0]['against']);
        $this->assertSame(2, $decisive[0]['margin']);
    }
}
