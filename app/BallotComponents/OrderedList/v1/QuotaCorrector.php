<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

/**
 * Optional post-tally quota balancing over the determined top-K (the
 * "natural" order). Runs only when the cutoff is settled: a contested
 * cutoff is always SURFACED first, never guessed at (D5). Minimal-
 * displacement: swaps the fewest members needed to satisfy a min/max
 * category quota, preserving the natural relative order of everyone not
 * swapped, and refuses to guess across an unresolved (surfaced) tie among
 * the swap candidates.
 */
final class QuotaCorrector
{
    /** @var array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool} */
    private array $result;

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $ranking
     * @param array{remaining_seats:int,candidates:list<string>,internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>}|null $cutoffDecision
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     * @param array<string,string> $categories
     * @param array{category:string,type:string,count:int,binding:bool} $quota
     */
    public function __construct(array $ranking, ?array $cutoffDecision, array $bands, array $categories, array $quota, int $seats)
    {
        $binding = $quota['binding'];

        /** @var list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $naturalEntries */
        $naturalEntries = [];
        /** @var list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $belowCutEntries */
        $belowCutEntries = [];
        foreach ($ranking as $i => $entry) {
            if ($i < $seats) {
                $naturalEntries[] = $entry;
            } else {
                $belowCutEntries[] = $entry;
            }
        }
        $natural = array_map(static fn (array $e): string => $e['candidate'], $naturalEntries);

        // 1. Surface, rather than guess, while the cutoff itself is contested.
        if ($cutoffDecision !== null) {
            $order = [];
            foreach ($ranking as $entry) {
                if ($entry['status'] === 'elected') {
                    $order[] = $entry['candidate'];
                }
            }
            $this->warnings[] = 'quota surfaced: the cut is contested — resolve per your organization\'s rules';
            $this->result = [
                'order' => $order,
                'diff' => [],
                'infeasible' => false,
                'provisional' => true,
                'binding' => $binding,
            ];

            return;
        }

        $category = $quota['category'];

        // 2. A quota whose category no candidate carries is dropped, not guessed at.
        if (!in_array($category, array_values($categories), true)) {
            $this->warnings[] = 'quota category not among candidate categories — quota ignored';
            $this->result = [
                'order' => $natural,
                'diff' => [],
                'infeasible' => false,
                'provisional' => false,
                'binding' => $binding,
            ];

            return;
        }

        $inBand = $this->bandMembership($bands);

        $this->result = $quota['type'] === 'max'
            ? $this->applyMax($naturalEntries, $belowCutEntries, $categories, $category, $quota['count'], $binding, $inBand)
            : $this->applyMin($naturalEntries, $belowCutEntries, $categories, $category, $quota['count'], $binding, $inBand);
    }

    /** @return array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool} */
    public function result(): array
    {
        return $this->result;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     * @return array<string,bool>
     */
    private function bandMembership(array $bands): array
    {
        $members = [];
        foreach ($bands as $band) {
            foreach ($band['candidates'] as $c) {
                $members[$c] = true;
            }
        }

        return $members;
    }

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $naturalEntries
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $belowCutEntries
     * @param array<string,string> $categories
     * @param array<string,bool> $inBand
     * @return array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool}
     */
    private function applyMin(array $naturalEntries, array $belowCutEntries, array $categories, string $category, int $count, bool $binding, array $inBand): array
    {
        $natural = array_map(static fn (array $e): string => $e['candidate'], $naturalEntries);

        $inCutInCat = array_values(array_filter(
            $naturalEntries,
            static fn (array $e): bool => ($categories[$e['candidate']] ?? null) === $category
        ));
        $n = count($inCutInCat);

        if ($n >= $count) {
            return ['order' => $natural, 'diff' => [], 'infeasible' => false, 'provisional' => false, 'binding' => $binding];
        }

        $need = $count - $n;

        $promoteesEntries = array_values(array_filter(
            $belowCutEntries,
            static fn (array $e): bool => ($categories[$e['candidate']] ?? null) === $category
        ));

        $demoteesEntries = array_values(array_filter(
            $naturalEntries,
            static fn (array $e): bool => ($categories[$e['candidate']] ?? null) !== $category
        ));
        usort($demoteesEntries, static fn (array $a, array $b): int => $b['worst_pos'] <=> $a['worst_pos']);

        $totalInCategory = $n + count($promoteesEntries);

        if ($totalInCategory < $count || count($promoteesEntries) < $need || count($demoteesEntries) < $need) {
            $this->warnings[] = "quota infeasible: not enough {$category} candidates to satisfy the minimum";

            return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
        }

        $promotionSet = array_slice($promoteesEntries, 0, $need);
        $demotionSet = array_slice($demoteesEntries, 0, $need);

        if ($this->touchesBand($promotionSet, $demotionSet, $inBand)) {
            $this->warnings[] = "quota surfaced: a tie must be resolved (per your organization's rules) first";

            return ['order' => $natural, 'diff' => [], 'infeasible' => false, 'provisional' => true, 'binding' => $binding];
        }

        return $this->applySwap($naturalEntries, $demotionSet, $promotionSet, 'min_quota:' . $category, $binding);
    }

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $naturalEntries
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $belowCutEntries
     * @param array<string,string> $categories
     * @param array<string,bool> $inBand
     * @return array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool}
     */
    private function applyMax(array $naturalEntries, array $belowCutEntries, array $categories, string $category, int $count, bool $binding, array $inBand): array
    {
        $natural = array_map(static fn (array $e): string => $e['candidate'], $naturalEntries);

        $inCutInCat = array_values(array_filter(
            $naturalEntries,
            static fn (array $e): bool => ($categories[$e['candidate']] ?? null) === $category
        ));
        $n = count($inCutInCat);

        if ($n <= $count) {
            return ['order' => $natural, 'diff' => [], 'infeasible' => false, 'provisional' => false, 'binding' => $binding];
        }

        $need = $n - $count;

        $demoteesEntries = $inCutInCat;
        usort($demoteesEntries, static fn (array $a, array $b): int => $b['worst_pos'] <=> $a['worst_pos']);

        $promoteesEntries = array_values(array_filter(
            $belowCutEntries,
            static fn (array $e): bool => ($categories[$e['candidate']] ?? null) !== $category
        ));

        if (count($promoteesEntries) < $need) {
            $this->warnings[] = "quota infeasible: not enough non-{$category} candidates available";

            return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
        }

        $promotionSet = array_slice($promoteesEntries, 0, $need);
        $demotionSet = array_slice($demoteesEntries, 0, $need);

        if ($this->touchesBand($promotionSet, $demotionSet, $inBand)) {
            $this->warnings[] = "quota surfaced: a tie must be resolved (per your organization's rules) first";

            return ['order' => $natural, 'diff' => [], 'infeasible' => false, 'provisional' => true, 'binding' => $binding];
        }

        return $this->applySwap($naturalEntries, $demotionSet, $promotionSet, 'max_quota:' . $category, $binding);
    }

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $promotionSet
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $demotionSet
     * @param array<string,bool> $inBand
     */
    private function touchesBand(array $promotionSet, array $demotionSet, array $inBand): bool
    {
        foreach (array_merge($promotionSet, $demotionSet) as $e) {
            if ($inBand[$e['candidate']] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $naturalEntries
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $demotionSet
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $promotionSet
     * @return array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool}
     */
    private function applySwap(array $naturalEntries, array $demotionSet, array $promotionSet, string $reason, bool $binding): array
    {
        $demoteCandidates = array_map(static fn (array $e): string => $e['candidate'], $demotionSet);

        $order = [];
        foreach ($naturalEntries as $e) {
            if (in_array($e['candidate'], $demoteCandidates, true)) {
                continue;
            }
            $order[] = $e['candidate'];
        }

        $diff = [];
        foreach ($promotionSet as $e) {
            $order[] = $e['candidate'];
            $diff[] = ['candidate' => $e['candidate'], 'from' => 'below_cut', 'reason' => $reason];
        }

        return ['order' => $order, 'diff' => $diff, 'infeasible' => false, 'provisional' => false, 'binding' => $binding];
    }
}
