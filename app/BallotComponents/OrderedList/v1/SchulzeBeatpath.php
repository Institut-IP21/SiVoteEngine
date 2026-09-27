<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

/**
 * Schulze method (beatpath / strongest-path), margins variant: computes the
 * widest-path closure over the decisive pairwise margins and derives, for
 * every ordered pair, whether one candidate strictly outranks the other.
 *
 * Seed: p[i][j] = margin(i,j) when i decisively beats j, else no path
 * (represented as null, i.e. -infinity). Widest path (Floyd-Warshall): for
 * every intermediate k, p[i][j] = max(p[i][j], min(p[i][k], p[k][j])). Final
 * relation: i outranks j iff p[i][j] > p[j][i]; a genuine tie iff the two are
 * exactly equal (including "neither has a path", i.e. both null). This
 * relation is PROVEN transitive (Schulze's method is a Condorcet method that
 * always yields a transitive ranking -- see Schulze, "The Schulze Method of
 * Voting"), so `reachable()` needs no further transitive-closure step: it IS
 * the strict partial order PositionResolver expects, in the exact shape
 * `RankedPairsLock::reachable()` used to hand it.
 *
 * Unlike Ranked Pairs, this method is edge-processing-order-independent by
 * construction (Floyd-Warshall has no notion of "strongest edge first") --
 * there is no arbitrary-lock-order problem to work around here.
 */
final class SchulzeBeatpath
{
    /** @var list<string> */
    private array $roster;

    /**
     * Widest-path strength p[i][j]; null means "no path" (-infinity), i.e.
     * i never decisively beats j, directly or through any chain.
     *
     * @var array<string,array<string,int|null>>
     */
    private array $strength = [];

    /**
     * via[i][j] = the intermediate candidate whose split last improved
     * p[i][j] during widest-path relaxation, or null when p[i][j] is still
     * the direct seeded edge (or has no path at all). Used only to
     * reconstruct the strongest chain on demand for the disclosure view.
     *
     * @var array<string,array<string,string|null>>
     */
    private array $via = [];

    /**
     * @param list<array{winner:string,loser:string,margin:int,for:int,against:int}> $decisivePairs
     * @param list<string> $roster
     */
    public function __construct(array $decisivePairs, array $roster)
    {
        $this->roster = $roster;

        foreach ($roster as $i) {
            foreach ($roster as $j) {
                if ($i === $j) {
                    continue;
                }
                $this->strength[$i][$j] = null;
                $this->via[$i][$j] = null;
            }
        }

        foreach ($decisivePairs as $edge) {
            $this->strength[$edge['winner']][$edge['loser']] = $edge['margin'];
        }

        foreach ($roster as $through) {
            foreach ($roster as $i) {
                if ($i === $through) {
                    continue;
                }
                foreach ($roster as $j) {
                    if ($j === $i || $j === $through) {
                        continue;
                    }

                    $viaThrough = $this->widest($this->strength[$i][$through], $this->strength[$through][$j]);
                    if ($viaThrough === null) {
                        continue;
                    }

                    $current = $this->strength[$i][$j];
                    if ($current === null || $viaThrough > $current) {
                        $this->strength[$i][$j] = $viaThrough;
                        $this->via[$i][$j] = $through;
                    }
                }
            }
        }
    }

    /**
     * Widest-path strength matrix, p[i][j]. null = no path (-infinity).
     *
     * @return array<string,array<string,int|null>>
     */
    public function strength(): array
    {
        return $this->strength;
    }

    /**
     * The strict "outranks" relation derived from the widest-path matrix:
     * reachable[a][b] === true iff a strictly outranks b (p[a][b] > p[b][a]).
     * Exactly equal strengths (including "neither has a path") are a genuine
     * tie and report false in BOTH directions -- the engine never guesses.
     *
     * @return array<string,array<string,bool>>
     */
    public function reachable(): array
    {
        $result = [];
        foreach ($this->roster as $a) {
            foreach ($this->roster as $b) {
                if ($a === $b) {
                    $result[$a][$b] = false;

                    continue;
                }
                $result[$a][$b] = $this->beats($this->strength[$a][$b], $this->strength[$b][$a]);
            }
        }

        return $result;
    }

    /**
     * Every strict outrank relation with its strength and the strongest
     * chain of decisive wins that realizes it -- the "why A outranks B"
     * disclosure. Strongest first, then alphabetically by winner/loser for a
     * stable, deterministic display order.
     *
     * @return list<array{winner:string,loser:string,strength:int,path:list<string>}>
     */
    public function winners(): array
    {
        $out = [];
        foreach ($this->roster as $i) {
            foreach ($this->roster as $j) {
                if ($i === $j) {
                    continue;
                }

                $pij = $this->strength[$i][$j];
                $pji = $this->strength[$j][$i];
                if ($pij !== null && ($pji === null || $pij > $pji)) {
                    $out[] = [
                        'winner' => $i,
                        'loser' => $j,
                        'strength' => $pij,
                        'path' => $this->path($i, $j),
                    ];
                }
            }
        }

        usort($out, static function (array $a, array $b): int {
            return [-$a['strength'], $a['winner'], $a['loser']] <=> [-$b['strength'], $b['winner'], $b['loser']];
        });

        return $out;
    }

    private function widest(?int $a, ?int $b): ?int
    {
        if ($a === null || $b === null) {
            return null;
        }

        return min($a, $b);
    }

    private function beats(?int $pij, ?int $pji): bool
    {
        if ($pij === null) {
            return false;
        }
        if ($pji === null) {
            return true;
        }

        return $pij > $pji;
    }

    /**
     * Reconstructs the strongest i->j chain from the `via` split points.
     * Iterative (not recursive) decomposition of the (from, to) segment tree:
     * each segment either IS a direct/seeded edge (via === null, a leaf) or
     * splits into (from, mid) + (mid, to). Because Floyd-Warshall only ever
     * records `via[i][j] = through` when the through-split strictly improves
     * p[i][j] -- i.e. p[i][through] and p[through][j] were both already
     * finalized using ONLY intermediates considered in strictly earlier
     * passes -- this decomposition is guaranteed to terminate (a standard
     * path-reconstruction technique for Floyd-Warshall). The depth guard
     * below is a defensive backstop only; it should never trigger.
     *
     * @return list<string>
     */
    private function path(string $i, string $j): array
    {
        $result = [];
        /** @var list<array{0:string,1:string}> $stack */
        $stack = [[$i, $j]];
        $guard = 0;

        while ($stack !== []) {
            $guard++;
            if ($guard > 10_000) {
                return [$i, $j];
            }

            /** @var array{0:string,1:string} $segment */
            $segment = array_pop($stack);
            [$from, $to] = $segment;
            $mid = $this->via[$from][$to] ?? null;

            if ($mid === null) {
                $result[] = $from;

                continue;
            }

            $stack[] = [$mid, $to];
            $stack[] = [$from, $mid];
        }

        $result[] = $j;

        return $result;
    }
}
