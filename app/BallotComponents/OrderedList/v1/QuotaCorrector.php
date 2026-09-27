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

        $inBand = $this->bandMembership($bands);

        // 2. Alternation has no single "category" (it derives its two
        // groups from settings.categories itself), so it is dispatched
        // before the category-membership check below, which does not apply
        // to it.
        if ($quota['type'] === 'alternate') {
            $this->result = $this->applyAlternate($naturalEntries, $belowCutEntries, $categories, $binding, $inBand);

            return;
        }

        $category = $quota['category'];

        // 3. A quota whose category no candidate carries is dropped, not guessed at.
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
     * Alternation ("zipper") quota: an ordered slate that alternates between
     * exactly two categories, starting with the natural #1 candidate's own
     * group. Unlike min/max (which only swaps across the seat cutoff), this
     * may reorder members WITHIN the natural top-K too, since the natural
     * order is not guaranteed to already alternate.
     *
     * Precondition (D13, amended D13.1): the domain is what the zipper can
     * actually SEAT -- the fill below makes exactly `seats` picks, each
     * advancing one pool pointer, so it can never reach a candidate outside
     * the `seats`-length alternating slate of the two chosen groups. An
     * also-ran sitting below that seatable range (untagged, or a third
     * category) must NOT force infeasible, UNLESS choosing the second group
     * from below the cut would itself be a guess (D13.1). Group derivation:
     *
     *   1. Start group: `group1` = the natural #1 candidate's own category.
     *      Untagged -> infeasible, `alternate_warn_no_start_group` (the
     *      start group is genuinely undefined; fail honestly rather than
     *      falling back to a lower candidate).
     *   2. `topCats` = the distinct TAGGED categories among `naturalEntries`
     *      (the natural top-`seats` slate -- the set that WOULD be seated
     *      with no quota at all).
     *      - Guard 4a: any candidate in `naturalEntries` is untagged ->
     *        infeasible, `alternate_warn_extra_category` (an untagged
     *        front-runner would be silently displaced by a 2-group zipper).
     *      - Guard 4b: `|topCats| >= 3` -> infeasible,
     *        `alternate_warn_extra_category` (more than two categories
     *        among the leading candidates; a same-untracked candidate that
     *        ranks BELOW naturalEntries -- would not win anyway -- is
     *        correctly ignored, since topCats only looks at naturalEntries).
     *      - `|topCats| == 2`: `group2` = the other element of `topCats`.
     *        Both groups are anchored in the naturally-seated set, so any
     *        further category below the cut is provably never-seatable (the
     *        zipper only ever alternates group1/group2, and run-out stays
     *        within them) -- correctly ignored. This is the D13 fix for
     *        finding #1 (Case A/B stay feasible).
     *      - `|topCats| == 1` (monochromatic top-K): the second group can
     *        only come from below the cut, so it must be UNAMBIGUOUS.
     *        `otherCats` = the distinct tagged categories over the WHOLE
     *        roster (natural + below-cut) that are `!= group1`.
     *          - `|otherCats| == 0` -> infeasible,
     *            `alternate_warn_one_category` (only one category anywhere).
     *          - `|otherCats| == 1` -> `group2` = that sole other category
     *            (the central rebalance case: an all-group1 top-K vs a
     *            single below-cut group -- MUST stay feasible).
     *          - `|otherCats| >= 2` -> infeasible,
     *            `alternate_warn_ambiguous_second_group` (D13.1: two or more
     *            candidate categories could fill the second slot -- refuse
     *            rather than let whichever ranks highest silently win with
     *            no warning).
     *   3. The two pools: every candidate (natural + below-cut, natural
     *      order preserved) tagged group1 or group2 respectively; untagged
     *      or other-category candidates are excluded from both -- they can
     *      never be part of a group1/group2 zipper.
     *      - Guard 3: if the two pools together can't fill every seat ->
     *        infeasible, `alternate_warn_not_enough_candidates`. (By
     *        construction this is now always satisfied once guards 1/4a/4b
     *        and the monochromatic-branch guards pass -- every one of the
     *        `seats` naturalEntries is tagged group1 or group2, so the pools
     *        already sum to >= seats -- but the check is kept as the
     *        explicit invariant the run-out branch below relies on.)
     *
     * Fill: walk the alternating pattern seat by seat, taking the highest
     * natural-ranked not-yet-placed candidate of the wanted group; once a
     * group is exhausted, fill the rest from the other group (owner rule),
     * which always yields a full slate since guard 3 guarantees the two
     * pools together have >= seats members. A pick that lands on a member
     * of an unresolved band is surfaced as provisional -- exactly like
     * `applyMin`/`applyMax`'s `touchesBand`, the engine does not privilege
     * one tied candidate over another by array order. This also covers a
     * tie for the natural #1 spot itself: the very first pick is always the
     * highest-natural-ranked group1 member, so if that pick is itself in an
     * unresolved band, seat 1 already surfaces provisional.
     *
     * Diff (audit only -- `order` itself never changes because of this):
     * every candidate whose SEATED position differs from its natural one
     * gets a diff row (`reason:'alternate'`) -- not only below-cut
     * promotions. A below-cut entrant records `from:'below_cut'`; a
     * candidate who stayed in the top-K but moved position records
     * `from:'natural:<1-based prior rank>'`.
     *
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $naturalEntries
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $belowCutEntries
     * @param array<string,string> $categories
     * @param array<string,bool> $inBand
     * @return array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool}
     */
    private function applyAlternate(array $naturalEntries, array $belowCutEntries, array $categories, bool $binding, array $inBand): array
    {
        $natural = array_map(static fn (array $e): string => $e['candidate'], $naturalEntries);
        $seats = count($naturalEntries);

        if ($naturalEntries === []) {
            return ['order' => $natural, 'diff' => [], 'infeasible' => false, 'provisional' => false, 'binding' => $binding];
        }

        /** @var list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $allEntries */
        $allEntries = [...$naturalEntries, ...$belowCutEntries];

        // Guard 1 -- start group: the natural #1 candidate's own category.
        $group1 = $categories[$naturalEntries[0]['candidate']] ?? null;
        if ($group1 === null) {
            $this->warnings[] = __('components.orderedlist.alternate_warn_no_start_group');

            return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
        }

        // D13.1 -- topCats: the distinct TAGGED categories among
        // naturalEntries (the set that would be seated with no quota).
        $topCatsSet = [];
        $hasUntaggedInTop = false;
        foreach ($naturalEntries as $e) {
            $cat = $categories[$e['candidate']] ?? null;
            if ($cat === null) {
                $hasUntaggedInTop = true;
            } else {
                $topCatsSet[$cat] = true;
            }
        }

        // Guard 4a -- an untagged front-runner would be silently displaced.
        if ($hasUntaggedInTop) {
            $this->warnings[] = __('components.orderedlist.alternate_warn_extra_category');

            return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
        }

        // Guard 4b -- more than two categories among the leading candidates.
        if (count($topCatsSet) >= 3) {
            $this->warnings[] = __('components.orderedlist.alternate_warn_extra_category');

            return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
        }

        $group2 = null;

        if (count($topCatsSet) === 2) {
            // Both groups are anchored in naturalEntries; anything else
            // below the cut is provably never-seatable by the zipper.
            foreach (array_keys($topCatsSet) as $cat) {
                if ($cat !== $group1) {
                    $group2 = $cat;
                    break;
                }
            }
        } else {
            // Monochromatic top-K (topCatsSet === [group1 => true]): the
            // second group must come from below the cut, and must be
            // UNAMBIGUOUS across the whole roster.
            $otherCatsSet = [];
            foreach ($allEntries as $e) {
                $cat = $categories[$e['candidate']] ?? null;
                if ($cat !== null && $cat !== $group1) {
                    $otherCatsSet[$cat] = true;
                }
            }

            if (count($otherCatsSet) === 0) {
                $this->warnings[] = __('components.orderedlist.alternate_warn_one_category');

                return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
            }

            if (count($otherCatsSet) >= 2) {
                $this->warnings[] = __('components.orderedlist.alternate_warn_ambiguous_second_group');

                return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
            }

            foreach (array_keys($otherCatsSet) as $cat) {
                $group2 = $cat;
                break;
            }
        }

        // Unreachable in practice: |topCats|==2 always has a non-group1
        // element (group1 itself is one of the two), and the |topCats|==1
        // branch above already returns for 0 or >=2 other categories,
        // leaving exactly one to assign. Kept as an explicit, statically
        // provable guard rather than a `@var` cast over dead code.
        if ($group2 === null) {
            $this->warnings[] = __('components.orderedlist.alternate_warn_one_category');

            return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
        }

        // The two pools (natural order preserved). Untagged/other-category
        // candidates are excluded from both -- never reachable by the zipper.
        $poolStart = array_values(array_filter(
            $allEntries,
            static fn (array $e): bool => ($categories[$e['candidate']] ?? null) === $group1
        ));
        $poolOther = array_values(array_filter(
            $allEntries,
            static fn (array $e): bool => ($categories[$e['candidate']] ?? null) === $group2
        ));

        // Guard 3 -- the two pools together must be able to fill every seat.
        if (count($poolStart) + count($poolOther) < $seats) {
            $this->warnings[] = __('components.orderedlist.alternate_warn_not_enough_candidates');

            return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
        }

        // Fill: unchanged mechanics, running on the two pools defined above
        // (group1 = start). Guard 3 guarantees the run-out branch stays in
        // bounds.
        $ptrStart = 0;
        $ptrOther = 0;

        $order = [];
        $provisional = false;

        for ($i = 0; $i < $seats; $i++) {
            $wantStart = $i % 2 === 0;

            if ($wantStart) {
                if ($ptrStart < count($poolStart)) {
                    $pick = $poolStart[$ptrStart];
                    $ptrStart++;
                } else {
                    // Run-out (owner rule): fill from the other group.
                    $pick = $poolOther[$ptrOther];
                    $ptrOther++;
                }
            } else {
                if ($ptrOther < count($poolOther)) {
                    $pick = $poolOther[$ptrOther];
                    $ptrOther++;
                } else {
                    $pick = $poolStart[$ptrStart];
                    $ptrStart++;
                }
            }

            if ($inBand[$pick['candidate']] ?? false) {
                $provisional = true;
            }

            $order[] = $pick['candidate'];
        }

        if ($provisional) {
            $this->warnings[] = "quota surfaced: a tie must be resolved (per your organization's rules) first";

            return ['order' => $natural, 'diff' => [], 'infeasible' => false, 'provisional' => true, 'binding' => $binding];
        }

        // Finding #2: a diff row for every seated candidate whose position
        // differs from its natural one -- below-cut entrants AND within-
        // top-K reorders alike. `order` itself is unaffected; this is audit
        // metadata only.
        /** @var array<string,int> $naturalPos */
        $naturalPos = array_flip($natural);

        $diff = [];
        foreach ($order as $pos => $candidate) {
            if (!array_key_exists($candidate, $naturalPos)) {
                $diff[] = ['candidate' => $candidate, 'from' => 'below_cut', 'reason' => 'alternate'];
            } elseif ($naturalPos[$candidate] !== $pos) {
                $diff[] = ['candidate' => $candidate, 'from' => 'natural:' . ($naturalPos[$candidate] + 1), 'reason' => 'alternate'];
            }
        }

        return ['order' => $order, 'diff' => $diff, 'infeasible' => false, 'provisional' => false, 'binding' => $binding];
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
