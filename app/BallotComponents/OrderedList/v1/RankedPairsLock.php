<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

/**
 * Ranked Pairs lock-in: processes decisive winner->loser edges strongest
 * margin first, locking each edge unless it would close a cycle at (or
 * below) its own strength -- in which case it, and every other edge tied
 * with it inside the same strongly-connected component, is left unlocked
 * and surfaced instead of broken by an arbitrary tiebreak.
 *
 * Order-independent within a margin level: all edges sharing a margin are
 * evaluated against one shared strongly-connected-components snapshot (the
 * previously locked graph plus the whole level), not against each other
 * one at a time.
 */
final class RankedPairsLock
{
    /** @var list<string> */
    private array $roster;

    /** @var array<string,list<string>> */
    private array $locked = [];

    /** @var list<array{type:string,winner:string,loser:string,for:int,against:int,margin:int,members?:list<string>}> */
    private array $log = [];

    // --- Tarjan SCC working state (reset per computeSccMap() call) ---
    /** @var array<string,list<string>> */
    private array $tjAdj = [];

    /** @var array<string,int> */
    private array $tjIndices = [];

    /** @var array<string,int> */
    private array $tjLowlink = [];

    /** @var array<string,bool> */
    private array $tjOnStack = [];

    /** @var list<string> */
    private array $tjStack = [];

    /** @var array<string,int> */
    private array $tjSccOf = [];

    private int $tjIndex = 0;

    private int $tjSccCount = 0;

    /**
     * @param list<array{winner:string,loser:string,margin:int,for:int,against:int}> $decisivePairs
     * @param list<string> $roster
     */
    public function __construct(array $decisivePairs, array $roster)
    {
        $this->roster = $roster;

        foreach ($roster as $node) {
            $this->locked[$node] = [];
        }

        /** @var array<int,list<array{winner:string,loser:string,margin:int,for:int,against:int}>> $levels */
        $levels = [];
        foreach ($decisivePairs as $edge) {
            $levels[$edge['margin']][] = $edge;
        }
        krsort($levels);

        foreach ($levels as $edges) {
            $union = $this->buildUnionAdjacency($edges);
            $sccOf = $this->computeSccMap($union);

            foreach ($edges as $edge) {
                $winner = $edge['winner'];
                $loser = $edge['loser'];

                if ($sccOf[$winner] !== $sccOf[$loser]) {
                    $this->locked[$winner][] = $loser;
                    $this->log[] = [
                        'type' => 'locked',
                        'winner' => $winner,
                        'loser' => $loser,
                        'for' => $edge['for'],
                        'against' => $edge['against'],
                        'margin' => $edge['margin'],
                    ];
                } else {
                    $members = $this->sccMembers($sccOf, $sccOf[$winner]);
                    sort($members);
                    $this->log[] = [
                        'type' => 'skipped_cycle',
                        'winner' => $winner,
                        'loser' => $loser,
                        'for' => $edge['for'],
                        'against' => $edge['against'],
                        'margin' => $edge['margin'],
                        'members' => $members,
                    ];
                }
            }
        }
    }

    /** @return array<string,list<string>> */
    public function locked(): array
    {
        return $this->locked;
    }

    /** @return array<string,array<string,bool>> */
    public function reachable(): array
    {
        $result = [];
        foreach ($this->roster as $a) {
            foreach ($this->roster as $b) {
                $result[$a][$b] = false;
            }
        }

        foreach ($this->roster as $start) {
            /** @var array<string,bool> $visited */
            $visited = [];
            $this->dfsMark($start, $visited);
            foreach ($visited as $node => $true) {
                if ($node !== $start) {
                    $result[$start][$node] = true;
                }
            }
        }

        return $result;
    }

    /** @return list<array{type:string,winner:string,loser:string,for:int,against:int,margin:int,members?:list<string>}> */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * @param array<string,bool> $visited
     */
    private function dfsMark(string $node, array &$visited): void
    {
        if (isset($visited[$node])) {
            return;
        }
        $visited[$node] = true;

        foreach ($this->locked[$node] ?? [] as $next) {
            $this->dfsMark($next, $visited);
        }
    }

    /**
     * @param list<array{winner:string,loser:string,margin:int,for:int,against:int}> $edges
     * @return array<string,list<string>>
     */
    private function buildUnionAdjacency(array $edges): array
    {
        $adj = [];
        foreach ($this->roster as $node) {
            $adj[$node] = $this->locked[$node];
        }
        foreach ($edges as $edge) {
            $adj[$edge['winner']][] = $edge['loser'];
        }

        return $adj;
    }

    /**
     * @param array<string,list<string>> $adj
     * @return array<string,int> node => scc id
     */
    private function computeSccMap(array $adj): array
    {
        $this->tjAdj = $adj;
        $this->tjIndices = [];
        $this->tjLowlink = [];
        $this->tjOnStack = [];
        $this->tjStack = [];
        $this->tjSccOf = [];
        $this->tjIndex = 0;
        $this->tjSccCount = 0;

        foreach ($this->roster as $node) {
            if (!isset($this->tjIndices[$node])) {
                $this->strongConnect($node);
            }
        }

        return $this->tjSccOf;
    }

    private function strongConnect(string $v): void
    {
        $this->tjIndices[$v] = $this->tjIndex;
        $this->tjLowlink[$v] = $this->tjIndex;
        $this->tjIndex++;
        $this->tjStack[] = $v;
        $this->tjOnStack[$v] = true;

        foreach ($this->tjAdj[$v] ?? [] as $w) {
            if (!isset($this->tjIndices[$w])) {
                $this->strongConnect($w);
                $this->tjLowlink[$v] = min($this->tjLowlink[$v], $this->tjLowlink[$w]);
            } elseif ($this->tjOnStack[$w] ?? false) {
                $this->tjLowlink[$v] = min($this->tjLowlink[$v], $this->tjIndices[$w]);
            }
        }

        if ($this->tjLowlink[$v] === $this->tjIndices[$v]) {
            while (($w = array_pop($this->tjStack)) !== null) {
                $this->tjOnStack[$w] = false;
                $this->tjSccOf[$w] = $this->tjSccCount;
                if ($w === $v) {
                    break;
                }
            }
            $this->tjSccCount++;
        }
    }

    /**
     * @param array<string,int> $sccOf
     * @return list<string>
     */
    private function sccMembers(array $sccOf, int $sccId): array
    {
        $members = [];
        foreach ($sccOf as $node => $id) {
            if ($id === $sccId) {
                $members[] = $node;
            }
        }

        return $members;
    }
}
