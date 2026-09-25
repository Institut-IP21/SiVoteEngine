<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use Tests\TestCase;

class PositionResolverTest extends TestCase
{
    /**
     * @param list<string> $roster
     * @param list<array{0:string,1:string}> $pairs 'a reaches b' -- pass the FULL transitive closure
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

    /**
     * @param list<string> $roster
     * @param list<array{0:string,1:string,2:int}> $values
     * @return array<string,array<string,int>>
     */
    private function prefersMap(array $roster, array $values): array
    {
        $map = [];
        foreach ($roster as $a) {
            foreach ($roster as $b) {
                if ($a === $b) {
                    continue;
                }
                $map[$a][$b] = 0;
            }
        }
        foreach ($values as [$a, $b, $v]) {
            $map[$a][$b] = $v;
        }

        return $map;
    }

    public function test_clean_chain_seats_two(): void
    {
        $roster = ['A', 'B', 'C', 'D'];
        $reachable = $this->reachableMap($roster, [
            ['A', 'B'], ['A', 'C'], ['A', 'D'],
            ['B', 'C'], ['B', 'D'],
            ['C', 'D'],
        ]);
        $prefers = $this->prefersMap($roster, []);
        $pr = new PositionResolver($roster, $reachable, $prefers, 2);

        $byCandidate = [];
        foreach ($pr->ranking() as $entry) {
            $byCandidate[$entry['candidate']] = $entry;
        }
        $this->assertSame(['best_pos' => 1, 'worst_pos' => 1], ['best_pos' => $byCandidate['A']['best_pos'], 'worst_pos' => $byCandidate['A']['worst_pos']]);
        $this->assertSame(2, $byCandidate['B']['best_pos']);
        $this->assertSame(2, $byCandidate['B']['worst_pos']);
        $this->assertSame(3, $byCandidate['C']['best_pos']);
        $this->assertSame(3, $byCandidate['C']['worst_pos']);
        $this->assertSame(4, $byCandidate['D']['best_pos']);
        $this->assertSame(4, $byCandidate['D']['worst_pos']);

        $this->assertSame(['A', 'B'], $pr->elected());
        $this->assertSame([], $pr->bands());
        $this->assertNull($pr->cutoffDecision());
    }

    public function test_order_ambiguity_among_elected_seats_two(): void
    {
        $roster = ['A', 'B', 'C', 'D'];
        $reachable = $this->reachableMap($roster, [
            ['A', 'C'], ['A', 'D'],
            ['B', 'C'], ['B', 'D'],
        ]);
        $prefers = $this->prefersMap($roster, []);
        $pr = new PositionResolver($roster, $reachable, $prefers, 2);

        $byCandidate = [];
        foreach ($pr->ranking() as $entry) {
            $byCandidate[$entry['candidate']] = $entry;
        }
        $this->assertSame(1, $byCandidate['A']['best_pos']);
        $this->assertSame(2, $byCandidate['A']['worst_pos']);
        $this->assertSame(1, $byCandidate['B']['best_pos']);
        $this->assertSame(2, $byCandidate['B']['worst_pos']);
        $this->assertSame(3, $byCandidate['C']['best_pos']);
        $this->assertSame(4, $byCandidate['C']['worst_pos']);
        $this->assertSame(3, $byCandidate['D']['best_pos']);
        $this->assertSame(4, $byCandidate['D']['worst_pos']);

        $bands = $pr->bands();
        $this->assertCount(2, $bands);
        $this->assertSame(['A', 'B'], $bands[0]['candidates']);
        $this->assertSame([1, 2], $bands[0]['span']);
        $this->assertFalse($bands[0]['affects_cutoff']);
        $this->assertSame(['C', 'D'], $bands[1]['candidates']);
        $this->assertSame([3, 4], $bands[1]['span']);
        $this->assertFalse($bands[1]['affects_cutoff']);

        $this->assertSame(['A', 'B'], $pr->elected());
        $this->assertNull($pr->cutoffDecision());
    }

    public function test_contested_cutoff_seats_one(): void
    {
        $roster = ['A', 'B', 'C'];
        $reachable = $this->reachableMap($roster, [['A', 'C']]);
        $prefers = $this->prefersMap($roster, []);
        $pr = new PositionResolver($roster, $reachable, $prefers, 1);

        $byCandidate = [];
        foreach ($pr->ranking() as $entry) {
            $byCandidate[$entry['candidate']] = $entry;
        }
        $this->assertSame(1, $byCandidate['A']['best_pos']);
        $this->assertSame(2, $byCandidate['A']['worst_pos']);
        $this->assertSame(1, $byCandidate['B']['best_pos']);
        $this->assertSame(3, $byCandidate['B']['worst_pos']);
        $this->assertSame(2, $byCandidate['C']['best_pos']);
        $this->assertSame(3, $byCandidate['C']['worst_pos']);

        $bands = $pr->bands();
        $this->assertCount(1, $bands);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $bands[0]['candidates']);
        $this->assertSame([1, 3], $bands[0]['span']);
        $this->assertTrue($bands[0]['affects_cutoff']);
        $this->assertContains(['winner' => 'A', 'loser' => 'C'], $bands[0]['internal_constraints']);

        $cutoff = $pr->cutoffDecision();
        $this->assertNotNull($cutoff);
        $this->assertSame(1, $cutoff['remaining_seats']);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $cutoff['candidates']);

        $this->assertSame([], $pr->elected());
    }

    public function test_all_tied_seats_two(): void
    {
        $roster = ['A', 'B', 'C'];
        $reachable = $this->reachableMap($roster, []);
        $prefers = $this->prefersMap($roster, []);
        $pr = new PositionResolver($roster, $reachable, $prefers, 2);

        foreach ($pr->ranking() as $entry) {
            $this->assertSame(1, $entry['best_pos']);
            $this->assertSame(3, $entry['worst_pos']);
        }

        $bands = $pr->bands();
        $this->assertCount(1, $bands);
        $this->assertSame([1, 3], $bands[0]['span']);

        $cutoff = $pr->cutoffDecision();
        $this->assertNotNull($cutoff);
        $this->assertSame(2, $cutoff['remaining_seats']);
    }

    public function test_k_equals_n_all_elected_no_bands(): void
    {
        $roster = ['A', 'B', 'C'];
        $reachable = $this->reachableMap($roster, [
            ['A', 'B'], ['A', 'C'], ['B', 'C'],
        ]);
        $prefers = $this->prefersMap($roster, []);
        $pr = new PositionResolver($roster, $reachable, $prefers, 3);

        $this->assertSame(['A', 'B', 'C'], $pr->elected());
        $this->assertSame([], $pr->bands());
        $this->assertNull($pr->cutoffDecision());
    }

    public function test_never_approved_tail_straddles_the_cutoff(): void
    {
        // A, B are decisively ahead of everyone. C, D, E were never approved by
        // any ballot, so no decisive edge exists among them (mutual no-preference)
        // -- they form one wide tied tail that straddles the K=3 cutoff.
        $roster = ['A', 'B', 'C', 'D', 'E'];
        $reachable = $this->reachableMap($roster, [
            ['A', 'C'], ['A', 'D'], ['A', 'E'],
            ['B', 'C'], ['B', 'D'], ['B', 'E'],
        ]);
        $prefers = $this->prefersMap($roster, []);
        $pr = new PositionResolver($roster, $reachable, $prefers, 3);

        $this->assertSame(['A', 'B'], $pr->elected());

        $bands = $pr->bands();
        $this->assertCount(2, $bands);
        $this->assertSame(['A', 'B'], $bands[0]['candidates']);
        $this->assertFalse($bands[0]['affects_cutoff']);
        $this->assertEqualsCanonicalizing(['C', 'D', 'E'], $bands[1]['candidates']);
        $this->assertSame([3, 5], $bands[1]['span']);
        $this->assertTrue($bands[1]['affects_cutoff']);

        $cutoff = $pr->cutoffDecision();
        $this->assertNotNull($cutoff);
        $this->assertSame(1, $cutoff['remaining_seats']);
        $this->assertEqualsCanonicalizing(['C', 'D', 'E'], $cutoff['candidates']);
    }
}
