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

        // Alternation is dispatched BEFORE the contested-cut surfacing below:
        // a zipper consumes only each group's OWN order, so a tie at the cut
        // between candidates of DIFFERENT groups never affects it. Whether a
        // given tie matters is decided inside applyAlternate, which still
        // surfaces (never guesses) every tie the zipper really depends on.
        if ($quota['type'] === 'alternate') {
            $this->result = $this->applyAlternate($ranking, $cutoffDecision, $bands, $categories, $binding, $seats);

            return;
        }

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
     * Ties (D14, prod regression 2026-10-03). The zipper's output is a
     * function of exactly three things: the start group, the second group,
     * and each group's OWN internal order over the prefix it consumes. The
     * relative order of two candidates from DIFFERENT groups never matters
     * -- seat i takes "the best remaining member of group X" regardless of
     * where any member of group Y sits. So a tie is surfaced only when it
     * actually leaves one of those three things undecided:
     *
     *   - start group: every candidate that could be #1 (best_pos === 1)
     *     must share one category (untagged counts as its own value);
     *   - group set: under a contested cut, the categories of the surely-
     *     seated set (status elected) and of the possibly-seated set
     *     (elected + contested) must coincide -- every real top-K lies
     *     between the two, so equality means the tie cannot change which
     *     groups (or which guard) apply;
     *   - each pick: the picked candidate must strictly beat every not-yet-
     *     placed member of the pool it was taken from. Two candidates in
     *     different bands are always strictly ordered (non-overlapping
     *     position intervals), so only a same-band peer with no internal
     *     beatpath constraint can make a pick ambiguous.
     *
     * A contested natural cut therefore no longer short-circuits the
     * zipper: a tie between, e.g., an M and an F for the last natural seat
     * is irrelevant once the slate is built per group.
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
     *      Untagged -> infeasible, `alternate_warn_no_start_group`.
     *   2. `topCats` = the distinct TAGGED categories among the natural
     *      top-`seats` slate (under a contested cut: the possibly-seated
     *      set, after the group-set check above).
     *      - Guard 4a: any untagged candidate in it -> infeasible,
     *        `alternate_warn_extra_category`.
     *      - Guard 4b: `|topCats| >= 3` -> infeasible,
     *        `alternate_warn_extra_category`.
     *      - `|topCats| == 2`: `group2` = the other element of `topCats`.
     *      - `|topCats| == 1` (monochromatic top-K): `otherCats` = the
     *        distinct tagged categories over the WHOLE roster `!= group1`;
     *        0 -> infeasible (`alternate_warn_one_category`), 1 -> that one,
     *        >=2 -> infeasible (`alternate_warn_ambiguous_second_group`).
     *   3. The two pools: every candidate tagged group1 or group2 (natural
     *      order preserved); Guard 3: together they must fill every seat
     *      (`alternate_warn_not_enough_candidates`).
     *
     * Fill: walk the alternating pattern seat by seat, taking the highest
     * natural-ranked not-yet-placed candidate of the wanted group; once a
     * group is exhausted, fill the rest from the other group (owner rule).
     *
     * Provisional result: `order` is the DETERMINED PREFIX only -- the
     * seats the zipper fills identically however the organization resolves
     * the tie -- never the natural order (which, shown under the
     * "with alternation" heading, read as a broken zipper).
     *
     * Diff (audit only): every seated candidate whose position differs from
     * its natural one -- `from:'natural:<1-based rank>'` for a surely-seated
     * candidate, `from:'contested'` for one from a contested cut, and
     * `from:'below_cut'` for one from below it.
     *
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $ranking
     * @param array{remaining_seats:int,candidates:list<string>,internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>}|null $cutoffDecision
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     * @param array<string,string> $categories
     * @return array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool}
     */
    private function applyAlternate(array $ranking, ?array $cutoffDecision, array $bands, array $categories, bool $binding, int $seats): array
    {
        if ($ranking === []) {
            return ['order' => [], 'diff' => [], 'infeasible' => false, 'provisional' => false, 'binding' => $binding];
        }

        // The surely-seated and possibly-seated sets. With a settled cut
        // both are simply the natural top-`seats` slate.
        if ($cutoffDecision === null) {
            $surelySeated = array_slice($ranking, 0, $seats);
            $possiblySeated = $surelySeated;
        } else {
            $surelySeated = array_values(array_filter($ranking, static fn (array $e): bool => $e['status'] === 'elected'));
            $possiblySeated = array_values(array_filter($ranking, static fn (array $e): bool => $e['status'] !== 'excluded'));
        }
        $natural = array_map(static fn (array $e): string => $e['candidate'], $surelySeated);

        $infeasible = function (string $warningKey) use ($natural, $binding): array {
            $this->warnings[] = __($warningKey);

            return ['order' => $natural, 'diff' => [], 'infeasible' => true, 'provisional' => false, 'binding' => $binding];
        };

        // Start group must not depend on a tie for first place.
        $startCats = [];
        foreach ($ranking as $e) {
            if ($e['best_pos'] === 1) {
                $startCats[$categories[$e['candidate']] ?? "\0untagged"] = true;
            }
        }
        if (count($startCats) > 1) {
            return $this->surfaceAlternate([], $binding);
        }

        // The group set must not depend on how a contested cut resolves.
        $surelyCats = $this->categorySignature($surelySeated, $categories);
        $possiblyCats = $this->categorySignature($possiblySeated, $categories);
        if ($surelyCats !== $possiblyCats) {
            return $this->surfaceAlternate([], $binding);
        }

        // Guard 1 -- start group: the natural #1 candidate's own category.
        $group1 = $categories[$ranking[0]['candidate']] ?? null;
        if ($group1 === null) {
            return $infeasible('components.orderedlist.alternate_warn_no_start_group');
        }

        // D13.1 -- topCats: the distinct TAGGED categories among the
        // (tie-invariant, checked above) seated set.
        $topCatsSet = [];
        $hasUntaggedInTop = false;
        foreach ($possiblySeated as $e) {
            $cat = $categories[$e['candidate']] ?? null;
            if ($cat === null) {
                $hasUntaggedInTop = true;
            } else {
                $topCatsSet[$cat] = true;
            }
        }

        // Guard 4a -- an untagged front-runner would be silently displaced.
        // Guard 4b -- more than two categories among the leading candidates.
        if ($hasUntaggedInTop || count($topCatsSet) >= 3) {
            return $infeasible('components.orderedlist.alternate_warn_extra_category');
        }

        $group2 = null;

        if (count($topCatsSet) === 2) {
            // Both groups are anchored in the seated set; anything else
            // below the cut is provably never-seatable by the zipper.
            foreach (array_keys($topCatsSet) as $cat) {
                if ($cat !== $group1) {
                    $group2 = $cat;
                    break;
                }
            }
        } else {
            // Monochromatic top-K: the second group must come from below the
            // cut, and must be UNAMBIGUOUS across the whole roster.
            $otherCatsSet = [];
            foreach ($ranking as $e) {
                $cat = $categories[$e['candidate']] ?? null;
                if ($cat !== null && $cat !== $group1) {
                    $otherCatsSet[$cat] = true;
                }
            }

            if (count($otherCatsSet) === 0) {
                return $infeasible('components.orderedlist.alternate_warn_one_category');
            }

            if (count($otherCatsSet) >= 2) {
                return $infeasible('components.orderedlist.alternate_warn_ambiguous_second_group');
            }

            $group2 = array_key_first($otherCatsSet);
        }

        // Unreachable in practice (see the derivation above); kept as an
        // explicit, statically provable guard.
        if ($group2 === null) {
            return $infeasible('components.orderedlist.alternate_warn_one_category');
        }

        // The two pools (natural order preserved). Untagged/other-category
        // candidates are excluded from both -- never reachable by the zipper.
        $poolStart = array_values(array_map(
            static fn (array $e): string => $e['candidate'],
            array_filter($ranking, static fn (array $e): bool => ($categories[$e['candidate']] ?? null) === $group1)
        ));
        $poolOther = array_values(array_map(
            static fn (array $e): string => $e['candidate'],
            array_filter($ranking, static fn (array $e): bool => ($categories[$e['candidate']] ?? null) === $group2)
        ));

        // Guard 3 -- the two pools together must be able to fill every seat.
        if (count($poolStart) + count($poolOther) < $seats) {
            return $infeasible('components.orderedlist.alternate_warn_not_enough_candidates');
        }

        // Tie lookup: band index per candidate, and the strict beatpath
        // constraints inside each band.
        $bandOf = [];
        /** @var array<string,array<string,bool>> $beats */
        $beats = [];
        foreach ($bands as $bi => $band) {
            foreach ($band['candidates'] as $c) {
                $bandOf[$c] = $bi;
            }
            foreach ($band['internal_constraints'] as $con) {
                $beats[$con['winner']][$con['loser']] = true;
            }
        }

        $ptrStart = 0;
        $ptrOther = 0;
        $order = [];

        for ($i = 0; $i < $seats; $i++) {
            $wantStart = $i % 2 === 0;
            // Run-out (owner rule): once a group is exhausted, fill from the other.
            $fromStart = $wantStart ? $ptrStart < count($poolStart) : $ptrOther >= count($poolOther);

            if ($fromStart) {
                $pool = $poolStart;
                $ptr = $ptrStart++;
            } else {
                $pool = $poolOther;
                $ptr = $ptrOther++;
            }
            $pick = $pool[$ptr];

            // Ambiguous iff a not-yet-placed member of the SAME pool is tied
            // with the pick (same band, no strict beatpath between them).
            for ($j = $ptr + 1, $n = count($pool); $j < $n; $j++) {
                $rival = $pool[$j];
                if (isset($bandOf[$pick], $bandOf[$rival])
                    && $bandOf[$pick] === $bandOf[$rival]
                    && !($beats[$pick][$rival] ?? false)
                ) {
                    return $this->surfaceAlternate($order, $binding);
                }
            }

            $order[] = $pick;
        }

        /** @var array<string,int> $rankIndex */
        $rankIndex = [];
        $statusOf = [];
        foreach ($ranking as $idx => $e) {
            $rankIndex[$e['candidate']] = $idx;
            $statusOf[$e['candidate']] = $e['status'];
        }
        $naturalSet = array_flip($natural);

        $diff = [];
        foreach ($order as $pos => $candidate) {
            if (array_key_exists($candidate, $naturalSet)) {
                if ($rankIndex[$candidate] !== $pos) {
                    $diff[] = ['candidate' => $candidate, 'from' => 'natural:' . ($rankIndex[$candidate] + 1), 'reason' => 'alternate'];
                }
            } elseif (($statusOf[$candidate] ?? null) === 'contested') {
                $diff[] = ['candidate' => $candidate, 'from' => 'contested', 'reason' => 'alternate'];
            } else {
                $diff[] = ['candidate' => $candidate, 'from' => 'below_cut', 'reason' => 'alternate'];
            }
        }

        return ['order' => $order, 'diff' => $diff, 'infeasible' => false, 'provisional' => false, 'binding' => $binding];
    }

    /**
     * A surfaced alternation: only the determined prefix is reported.
     *
     * @param list<string> $prefix
     * @return array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool}
     */
    private function surfaceAlternate(array $prefix, bool $binding): array
    {
        $this->warnings[] = "quota surfaced: a tie must be resolved (per your organization's rules) first";

        return ['order' => $prefix, 'diff' => [], 'infeasible' => false, 'provisional' => true, 'binding' => $binding];
    }

    /**
     * Sorted distinct categories of a candidate set, untagged as its own value.
     *
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $entries
     * @param array<string,string> $categories
     * @return list<string>
     */
    private function categorySignature(array $entries, array $categories): array
    {
        $set = [];
        foreach ($entries as $e) {
            $set[$categories[$e['candidate']] ?? "\0untagged"] = true;
        }
        $keys = array_map('strval', array_keys($set));
        sort($keys);

        return $keys;
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
