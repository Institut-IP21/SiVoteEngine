<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

/**
 * Builds the pairwise "prefers" matrix from counted ballots and derives the
 * decisive winner->loser edges with their margin strength (voters preferring
 * the winner minus voters preferring the loser) -- the input the Schulze
 * beatpath resolver seeds its widest-path matrix from.
 *
 * A ballot is a `list<string>` of approved labels, most-preferred first
 * (distinct, in-roster, by contract of the caller). For an ordered pair
 * (x, y): x counts as preferred over y on a ballot iff x is approved and y is
 * not, or both are approved and x appears before y. Neither-approved
 * contributes no preference either way.
 */
final class PairwiseMatrix
{
    /** @var array<string,array<string,int>> prefers[x][y] for every ordered in-roster pair, x!=y */
    private array $prefers = [];

    /** @var list<string> */
    private array $roster;

    /**
     * @param list<list<string>> $ballots
     * @param list<string> $roster
     */
    public function __construct(array $ballots, array $roster)
    {
        $this->roster = $roster;

        foreach ($roster as $x) {
            foreach ($roster as $y) {
                if ($x === $y) {
                    continue;
                }
                $this->prefers[$x][$y] = 0;
            }
        }

        foreach ($ballots as $ballot) {
            $posOf = array_flip($ballot);

            foreach ($roster as $x) {
                foreach ($roster as $y) {
                    if ($x === $y) {
                        continue;
                    }

                    $xPos = $posOf[$x] ?? null;
                    $yPos = $posOf[$y] ?? null;

                    $xPreferred = $xPos !== null && ($yPos === null || $xPos < $yPos);

                    if ($xPreferred) {
                        $this->prefers[$x][$y]++;
                    }
                }
            }
        }
    }

    /** @return list<string> */
    public function candidates(): array
    {
        return $this->roster;
    }

    public function prefers(string $x, string $y): int
    {
        return $this->prefers[$x][$y] ?? 0;
    }

    /** @return array<string,array<string,int>> */
    public function matrix(): array
    {
        return $this->prefers;
    }

    /**
     * @return list<array{winner:string,loser:string,margin:int,for:int,against:int}>
     */
    public function decisivePairs(): array
    {
        $edges = [];
        $n = count($this->roster);

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $x = $this->roster[$i];
                $y = $this->roster[$j];
                $fx = $this->prefers($x, $y);
                $fy = $this->prefers($y, $x);

                if ($fx === $fy) {
                    continue;
                }

                if ($fx > $fy) {
                    $edges[] = ['winner' => $x, 'loser' => $y, 'margin' => $fx - $fy, 'for' => $fx, 'against' => $fy];
                } else {
                    $edges[] = ['winner' => $y, 'loser' => $x, 'margin' => $fy - $fx, 'for' => $fy, 'against' => $fx];
                }
            }
        }

        return $edges;
    }
}
