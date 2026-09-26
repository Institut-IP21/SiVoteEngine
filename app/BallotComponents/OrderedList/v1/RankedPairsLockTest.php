<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use Tests\TestCase;

class RankedPairsLockTest extends TestCase
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

    public function test_clean_chain_locks_all(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 3],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 2],
            ['winner' => 'A', 'loser' => 'C', 'margin' => 4],
        ]);
        $lock = new RankedPairsLock($decisive, ['A', 'B', 'C']);

        $reachable = $lock->reachable();
        $this->assertTrue($reachable['A']['B']);
        $this->assertTrue($reachable['A']['C']);
        $this->assertTrue($reachable['B']['C']);
        $this->assertFalse($reachable['C']['A']);

        $log = $lock->log();
        $this->assertSame('A', $log[0]['winner']);
        $this->assertSame('C', $log[0]['loser']);
        $this->assertSame(4, $log[0]['margin']);

        foreach ($log as $entry) {
            $this->assertSame('locked', $entry['type']);
        }
    }

    public function test_decisive_cycle_skips_the_weakest_edge(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 3],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 2],
            ['winner' => 'C', 'loser' => 'A', 'margin' => 1],
        ]);
        $lock = new RankedPairsLock($decisive, ['A', 'B', 'C']);

        $reachable = $lock->reachable();
        $this->assertTrue($reachable['A']['B']);
        $this->assertTrue($reachable['A']['C']);
        $this->assertTrue($reachable['B']['C']);
        $this->assertFalse($reachable['C']['A']);

        $log = $lock->log();
        $skipped = array_values(array_filter($log, static fn (array $e): bool => $e['type'] === 'skipped_cycle'));
        $this->assertCount(1, $skipped);
        $this->assertSame('C', $skipped[0]['winner']);
        $this->assertSame('A', $skipped[0]['loser']);
        $this->assertArrayHasKey('members', $skipped[0]);
        $members = $skipped[0]['members'] ?? [];
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $members);

        $locked = array_values(array_filter($log, static fn (array $e): bool => $e['type'] === 'locked'));
        $this->assertCount(2, $locked);
    }

    public function test_symmetric_cycle_all_skipped(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 1],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 1],
            ['winner' => 'C', 'loser' => 'A', 'margin' => 1],
        ]);
        $lock = new RankedPairsLock($decisive, ['A', 'B', 'C']);

        foreach ($lock->locked() as $losers) {
            $this->assertSame([], $losers);
        }

        $reachable = $lock->reachable();
        foreach ($reachable as $row) {
            foreach ($row as $value) {
                $this->assertFalse($value);
            }
        }

        foreach ($lock->log() as $entry) {
            $this->assertSame('skipped_cycle', $entry['type']);
        }
    }

    /**
     * Regression: a strong-locked edge (A>B, margin 5) at the top level must
     * not be perturbed by an equal-margin cycle among OTHER nodes at a
     * weaker level (B>C and C>A, both margin 3). Only A>B locks; the margin-3
     * level is itself a 3-cycle (A->B is already locked from a higher level,
     * so the margin-3 level's own edges B->C and C->A, together with the
     * already-locked A->B, form a cycle A->B->C->A) and both of ITS edges are
     * skipped as one strongly-connected component -- distinct margins do not
     * imply a clean order once a stronger edge has already closed part of
     * the loop.
     */
    public function test_strong_locked_edge_survives_an_equal_margin_cycle_at_a_later_level(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 5],
            ['winner' => 'B', 'loser' => 'C', 'margin' => 3],
            ['winner' => 'C', 'loser' => 'A', 'margin' => 3],
        ]);
        $lock = new RankedPairsLock($decisive, ['A', 'B', 'C']);

        $log = $lock->log();
        $this->assertSame('locked', $log[0]['type']);
        $this->assertSame('A', $log[0]['winner']);
        $this->assertSame('B', $log[0]['loser']);
        $this->assertSame(5, $log[0]['margin']);

        // The margin-3 level: B->C and C->A, unioned with the already-locked
        // A->B, forms the cycle A->B->C->A -- both of that level's edges are
        // skipped as one SCC; only the margin-5 edge is ever locked.
        $skipped = array_values(array_filter($log, static fn (array $e): bool => $e['type'] === 'skipped_cycle'));
        $this->assertCount(2, $skipped);
        foreach ($skipped as $entry) {
            $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $entry['members'] ?? []);
        }

        $locked = array_values(array_filter($log, static fn (array $e): bool => $e['type'] === 'locked'));
        $this->assertCount(1, $locked);

        $reachable = $lock->reachable();
        $this->assertTrue($reachable['A']['B']);
        $this->assertFalse($reachable['B']['C']);
        $this->assertFalse($reachable['C']['A']);
        $this->assertFalse($reachable['A']['C']);
    }

    public function test_margins_ordering_processes_strongest_first(): void
    {
        $decisive = $this->pairs([
            ['winner' => 'A', 'loser' => 'B', 'margin' => 2, 'for' => 6, 'against' => 4],
            ['winner' => 'C', 'loser' => 'D', 'margin' => 5, 'for' => 5, 'against' => 0],
        ]);
        $lock = new RankedPairsLock($decisive, ['A', 'B', 'C', 'D']);

        $log = $lock->log();
        $this->assertSame('C', $log[0]['winner']);

        foreach ($log as $entry) {
            $this->assertSame('locked', $entry['type']);
        }

        $reachable = $lock->reachable();
        $this->assertTrue($reachable['A']['B']);
        $this->assertTrue($reachable['C']['D']);
    }
}
