<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

/**
 * Turns a locked partial order (the Ranked-Pairs reachable closure) into
 * position intervals per candidate, seat-status classification, overlap
 * bands (unresolved ties), and -- when exactly one band straddles the seat
 * cutoff -- the contested-cutoff decision the runner needs to resolve.
 */
final class PositionResolver
{
    /** @var list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> */
    private array $ranking;

    /** @var list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> */
    private array $bands;

    /** @var array{remaining_seats:int,candidates:list<string>,internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>}|null */
    private ?array $cutoffDecision;

    private readonly int $seats;

    /**
     * @param list<string> $roster
     * @param array<string,array<string,bool>> $reachable
     * @param array<string,array<string,int>> $prefers
     */
    public function __construct(array $roster, array $reachable, array $prefers, int $seats)
    {
        $this->seats = $seats;
        $n = count($roster);
        $rosterIndex = array_flip($roster);

        /** @var list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string,_idx:int}> $entries */
        $entries = [];
        foreach ($roster as $x) {
            $above = 0;
            $below = 0;
            foreach ($roster as $y) {
                if ($y === $x) {
                    continue;
                }
                if ($reachable[$y][$x] ?? false) {
                    $above++;
                }
                if ($reachable[$x][$y] ?? false) {
                    $below++;
                }
            }
            $best = $above + 1;
            $worst = $n - $below;
            $determined = $best === $worst;
            $status = $worst <= $this->seats ? 'elected' : ($best > $this->seats ? 'excluded' : 'contested');

            $entries[] = [
                'candidate' => $x,
                'best_pos' => $best,
                'worst_pos' => $worst,
                'determined' => $determined,
                'status' => $status,
                '_idx' => $rosterIndex[$x] ?? 0,
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            return [$a['best_pos'], $a['worst_pos'], $a['_idx']] <=> [$b['best_pos'], $b['worst_pos'], $b['_idx']];
        });

        $this->ranking = array_map(static function (array $e): array {
            return [
                'candidate' => $e['candidate'],
                'best_pos' => $e['best_pos'],
                'worst_pos' => $e['worst_pos'],
                'determined' => $e['determined'],
                'status' => $e['status'],
            ];
        }, $entries);

        $this->bands = $this->buildBands($prefers, $reachable);
        $this->cutoffDecision = $this->buildCutoffDecision();
    }

    /** @return list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> */
    public function ranking(): array
    {
        return $this->ranking;
    }

    /** @return list<string> */
    public function elected(): array
    {
        $elected = [];
        foreach ($this->ranking as $entry) {
            if ($entry['status'] === 'elected') {
                $elected[] = $entry['candidate'];
            }
        }

        return $elected;
    }

    /** @return list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> */
    public function bands(): array
    {
        return $this->bands;
    }

    /** @return array{remaining_seats:int,candidates:list<string>,internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>}|null */
    public function cutoffDecision(): ?array
    {
        return $this->cutoffDecision;
    }

    /**
     * @param array<string,array<string,int>> $prefers
     * @param array<string,array<string,bool>> $reachable
     * @return list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}>
     */
    private function buildBands(array $prefers, array $reachable): array
    {
        $bands = [];
        $count = count($this->ranking);
        if ($count === 0) {
            return $bands;
        }

        $current = [$this->ranking[0]];
        $maxWorst = $this->ranking[0]['worst_pos'];

        for ($i = 1; $i < $count; $i++) {
            $c = $this->ranking[$i];
            if ($c['best_pos'] <= $maxWorst) {
                $current[] = $c;
                $maxWorst = max($maxWorst, $c['worst_pos']);
            } else {
                $band = $this->closeBand($current, $maxWorst, $prefers, $reachable);
                if ($band !== null) {
                    $bands[] = $band;
                }
                $current = [$c];
                $maxWorst = $c['worst_pos'];
            }
        }

        $band = $this->closeBand($current, $maxWorst, $prefers, $reachable);
        if ($band !== null) {
            $bands[] = $band;
        }

        return $bands;
    }

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $group
     * @param array<string,array<string,int>> $prefers
     * @param array<string,array<string,bool>> $reachable
     * @return array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}|null
     */
    private function closeBand(array $group, int $maxWorst, array $prefers, array $reachable): ?array
    {
        if (count($group) < 2) {
            return null;
        }

        $members = array_map(static fn (array $e): string => $e['candidate'], $group);
        $minBest = $group[0]['best_pos'];

        $internalConstraints = [];
        $headToHead = [];
        foreach ($members as $x) {
            foreach ($members as $y) {
                if ($x === $y) {
                    continue;
                }
                if ($reachable[$x][$y] ?? false) {
                    $internalConstraints[] = ['winner' => $x, 'loser' => $y];
                }
                $headToHead[$x][$y] = $prefers[$x][$y] ?? 0;
            }
        }

        return [
            'candidates' => $members,
            'span' => [$minBest, $maxWorst],
            'internal_constraints' => $internalConstraints,
            'head_to_head' => $headToHead,
            'affects_cutoff' => $minBest <= $this->seats && $maxWorst > $this->seats,
        ];
    }

    /** @return array{remaining_seats:int,candidates:list<string>,internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>}|null */
    private function buildCutoffDecision(): ?array
    {
        foreach ($this->bands as $band) {
            if (!$band['affects_cutoff']) {
                continue;
            }

            $firstMember = $band['candidates'][0] ?? null;
            $i = 0;
            if ($firstMember !== null) {
                foreach ($this->ranking as $idx => $entry) {
                    if ($entry['candidate'] === $firstMember) {
                        $i = $idx;
                        break;
                    }
                }
            }

            return [
                'remaining_seats' => $this->seats - $i,
                'candidates' => $band['candidates'],
                'internal_constraints' => $band['internal_constraints'],
                'head_to_head' => $band['head_to_head'],
            ];
        }

        return null;
    }
}
