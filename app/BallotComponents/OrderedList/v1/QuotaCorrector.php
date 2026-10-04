<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

/**
 * Optional post-tally category quota (min / max / alternation) over the
 * Schulze result -- EXACT BY CONSTRUCTION (D15, 2026-10-04).
 *
 * The votes settle a strict partial order, not a total one: genuine ties
 * are left as "bands". The engine never breaks a tie, so the question the
 * quota must answer is "what slate results however the organization
 * resolves the ties?". The answer reports only what EVERY resolution (every
 * linear extension of the partial order) agrees on: the slate when they all
 * agree (`provisional:false`), otherwise the common prefix (`order`), the
 * candidates seated in every resolution (`seated`), those seated in some
 * but not all (`contested`), and the seats whose occupant is the same in
 * all of them (`positions`). Nothing is guessed, and nothing every
 * resolution agrees on is withheld.
 *
 * How (no enumeration of whole orders):
 *
 *   - Each quota rule is written as a small ONLINE automaton that reads a
 *     total order front to back and decides, the moment it reads a
 *     candidate, which slate seat (if any) that candidate takes. Its state
 *     is tiny (a few counters / the two alternation groups), never the
 *     order read so far.
 *   - The linear extensions are walked as a dynamic program over
 *     (still-unplaced candidates, automaton state). Two prefixes that
 *     placed the same set with the same automaton state have identical
 *     futures, so each such state is solved once (memoised): order ties
 *     among candidates cost a set, not a factorial.
 *   - A state's answer is a constant-size SUMMARY of every slate its
 *     completions produce (one representative slate, whether they differ,
 *     the seat-by-seat agreement, intersection and union) -- never the
 *     slates themselves -- so memory is bounded by the number of states.
 *   - Candidates that can come next and are indistinguishable to the
 *     automaton (same observed class, same relations to everyone left) are
 *     explored once; the others' summaries are the representative's with
 *     the two names swapped.
 *
 * If the state space grows past {@see self::MAX_NODES} the result fails
 * safe: provisional, nothing certain, every candidate contested, flagged
 * `too_complex`.
 *
 * The partial order is rebuilt from PositionResolver's output: candidates
 * in different bands are always strictly ordered by ranking index (two
 * incomparable candidates necessarily have overlapping position intervals,
 * so they land in the same band); inside a band, `internal_constraints`
 * lists every strict beatpath pair.
 *
 * @phpstan-type Hyp array{0:string,1:bool,2:string,3:string,4:string}
 * @phpstan-type Summary array<string,Hyp>
 * @phpstan-type State array{lv:int,e:int,g1:?string,g2:?string,inf:?string}
 * @phpstan-type Scenario array{order:list<string>,infeasible:bool,forced:array<string,array{0:int,1:int}>,prefixes:list<list<int>>}
 * @phpstan-type TieOption array{when:list<array{ahead:string,behind:list<string>}>,seats:array<int,string>,out:list<string>,infeasible:bool}
 * @phpstan-type Tie array{seats:list<int>,options:list<TieOption>}
 * @phpstan-type Result array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,partly_infeasible:bool,provisional:bool,binding:bool,too_complex:bool,seated:list<string>,contested:list<string>,positions:array<string,int>,scenarios:list<Tie>|null}
 */
final class QuotaCorrector
{
    public const int MAX_NODES = 100000;

    /** Distinct tie outcomes worth spelling out one by one. */
    public const int MAX_SCENARIOS = 6;

    /** Tie-resolution branches walked while explaining them. */
    private const int MAX_BRANCHES = 256;

    /**
     * Candidates whose seat is still open beyond which no short explanation
     * can exist (7 tied for one seat is already 7 options), so none is tried.
     */
    private const int MAX_OPEN_CANDIDATES = 12;

    /** State budget for explaining the ties (the explanation is optional). */
    private const int MAX_EXPLAIN_NODES = 20000;

    /** Candidates are packed one byte each up to here, two bytes beyond. */
    private const int ONE_BYTE_CANDIDATES = 254;

    private const int MAX_CANDIDATES = 65000;

    /** Memory the memo may add on top of what the request already uses. */
    private const int MEMORY_HEADROOM = 64 * 1024 * 1024;

    /** @var 1|2 bytes per packed candidate */
    private int $w = 1;

    /** Seat not assigned (yet) in a summary. */
    private string $unset = "\xFF";

    /** Seat occupied by different candidates in different resolutions. */
    private string $mixed = "\xFE";

    private int $memoryCeiling = PHP_INT_MAX;

    /** @var Result */
    private array $result;

    /** @var list<string> */
    private array $warnings = [];

    /** @var list<string> candidate per ranking index */
    private array $roster = [];

    /** @var array<string,int> candidate => ranking index */
    private array $index = [];

    private int $n;

    /** Seats actually filled: min(seats, candidates). */
    private int $k;

    /** @var list<string> per candidate: '1' at every candidate that beats it */
    private array $pred = [];

    /** @var list<string> per candidate: '1' at every candidate it beats */
    private array $succ = [];

    /** 'natural' (top-k as is), 'minmax' or 'alternate'. */
    private string $mode;

    private bool $naturalInfeasible = false;

    private ?string $naturalWarning = null;

    /** @var array<int,bool> min/max: candidate is on the side that may LEAVE the top */
    private array $leaveSide = [];

    /** min/max: the first `keepBound` leave-side candidates of the top stay. */
    private int $keepBound = 0;

    /** @var array<int,?string> */
    private array $categoryOf = [];

    /** @var array<string,string> category => candidate mask */
    private array $categoryMask = [];

    /** @var array<string,array<string,list<int>>> "g1\0g2" => [group => its seats, in order] */
    private array $patterns = [];

    /** @var array<string,string> state => packed Summary ({@see pack()}) */
    private array $memo = [];

    /** @var array<string,array{infeasible:bool,warnings:list<string>}> */
    private array $hypMeta = [];

    private int $nodes = 0;

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $ranking
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     * @param array<string,string> $categories
     * @param array{category:string,type:string,count:int,binding:bool} $quota
     */
    public function __construct(
        private readonly array $ranking,
        array $bands,
        private readonly array $categories,
        private readonly array $quota,
        private readonly int $seats,
        private readonly int $maxNodes = self::MAX_NODES,
        private readonly bool $closedForms = true,
    ) {
        if ($ranking === []) {
            $this->n = 0;
            $this->k = 0;
            $this->mode = 'natural';
            $this->result = $this->shape([], [], false, false, false, false, [], [], []);

            return;
        }

        foreach ($ranking as $i => $entry) {
            $this->roster[] = (string) $entry['candidate'];
            $this->index[(string) $entry['candidate']] = $i;
        }
        $this->n = count($this->roster);
        $this->k = min($seats, $this->n);

        $this->buildOrder($bands);
        $this->configure();

        $closed = $this->closedForm();
        if ($closed !== null) {
            $this->result = $closed;

            return;
        }

        try {
            if ($this->n > self::MAX_CANDIDATES) {
                throw new QuotaTooComplex();
            }
            if ($this->n > self::ONE_BYTE_CANDIDATES) {
                $this->w = 2;
                $this->unset = "\xFF\xFF";
                $this->mixed = "\xFE\xFE";
            }
            $this->memoryCeiling = $this->memoryCeiling();
            $summary = $this->solve(str_repeat('1', $this->n), 0, ['lv' => 0, 'e' => 0, 'g1' => null, 'g2' => null, 'inf' => null]);
        } catch (QuotaTooComplex) {
            $this->warnings[] = __('components.orderedlist.quota_warn_too_complex');
            $this->result = $this->fallback();

            return;
        } finally {
            $this->memo = [];
        }

        $this->result = $this->aggregate($summary);
    }

    /** @return Result */
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
     * How the open ties decide a provisional slate, as independent TIES:
     * each covers some of the undecided seats and lists its options -- who
     * takes those seats (`seats`, 1-based), who of the tied candidates then
     * misses out (`out`), and the condition that leads there (`when`: each
     * `ahead` candidate placed before its `behind` ones). Ties that do not
     * influence each other are reported separately, so two unrelated ties
     * read as two short lists, not every combination of both.
     *
     * A whole-slate condition is EXACT -- every resolution meeting it gives
     * that slate, and no other slate's resolutions meet it -- or left empty
     * when no such plain condition exists. `infeasible` marks a resolution
     * in which the quota cannot apply (the votes-alone top stands there).
     *
     * Null when the slate is final, the quota fell back to the fail-safe,
     * or more than {@see self::MAX_SCENARIOS} options would be needed (the
     * page then just names who is still tied).
     *
     * @return list<Tie>|null
     */
    public function scenarios(): ?array
    {
        $outcomes = $this->outcomes();

        return $outcomes === null ? null : $this->ties($outcomes);
    }

    /**
     * Every distinct slate the open ties can produce, with the tie-order
     * relations all its resolutions share (`forced`) and the decision-tree
     * branches (`prefixes`) that lead to it.
     *
     * @return list<Scenario>|null
     */
    private function outcomes(): ?array
    {
        if (!$this->result['provisional'] || $this->result['too_complex'] || $this->n === 0) {
            return null;
        }
        $open = count($this->result['contested']) + count($this->result['seated']) - count($this->result['positions']);
        if ($open > self::MAX_OPEN_CANDIDATES) {
            return null;
        }

        if ($this->n > self::ONE_BYTE_CANDIDATES) {
            $this->w = 2;
            $this->unset = "\xFF\xFF";
            $this->mixed = "\xFE\xFE";
        }
        $this->nodes = max(0, $this->maxNodes - self::MAX_EXPLAIN_NODES);
        $this->memoryCeiling = $this->memoryCeiling();
        $leaves = [];
        try {
            $this->branch(str_repeat('1', $this->n), 0, ['lv' => 0, 'e' => 0, 'g1' => null, 'g2' => null, 'inf' => null], [], $leaves);
        } catch (QuotaTooComplex) {
            return null;
        } finally {
            $this->memo = [];
        }

        /** @var array<string,list<array{prefix:list<int>,infeasible:bool}>> $byOutcome */
        $byOutcome = [];
        /** @var array<string,list<string>> $slates */
        $slates = [];
        foreach ($leaves as $leaf) {
            $key = implode("\0", $leaf['order']) . ($leaf['infeasible'] ? "\0!" : '');
            $byOutcome[$key][] = ['prefix' => $leaf['prefix'], 'infeasible' => $leaf['infeasible']];
            $slates[$key] = $leaf['order'];
        }
        if (count($byOutcome) > self::MAX_SCENARIOS ** 2) {
            return null;
        }

        $scenarios = [];
        foreach ($byOutcome as $key => $group) {
            $common = null;
            foreach ($group as $leaf) {
                $relations = $this->forcedRelations($leaf['prefix']);
                $common = $common === null ? $relations : array_intersect_key($common, $relations);
            }
            $scenarios[] = [
                'order' => $slates[$key],
                'infeasible' => $group[0]['infeasible'],
                'forced' => $common ?? [],
                'prefixes' => array_map(static fn (array $leaf): array => $leaf['prefix'], $group),
            ];
        }

        return $scenarios;
    }

    /**
     * Split the possible slates into independent ties: undecided seats
     * whose occupants vary together form one tie; seats that vary
     * independently of each other are separate ties. Factored only when the
     * slates are exactly every combination of the ties' options (and the
     * quota applies in all of them); otherwise one tie over every undecided
     * seat, one option per slate.
     *
     * @param list<Scenario> $outcomes
     * @return list<Tie>|null
     */
    private function ties(array $outcomes): ?array
    {
        $open = [];
        for ($s = 0; $s < $this->k; $s++) {
            $values = array_unique(array_map(static fn (array $o): string => $o['order'][$s] ?? '', $outcomes));
            if (count($values) > 1) {
                $open[] = $s;
            }
        }
        // Occupants of the given seats (plus whether the quota applies, so a
        // votes-alone slate that happens to match the zipper stays apart).
        $project = static fn (array $o, array $seats): string => implode("\0", array_map(static fn (int $s): string => $o['order'][$s] ?? '', $seats)) . ($o['infeasible'] ? "\0!" : '');
        $distinct = static fn (array $seats): int => count(array_unique(array_map(static fn (array $o): string => $project($o, $seats), $outcomes)));

        // Merge seat groups until every pair varies independently.
        $blocks = array_map(static fn (int $s): array => [$s], $open);
        // Splitting needs the quota to apply alike in every resolution.
        $mixedFeasibility = count(array_unique(array_map(static fn (array $o): bool => $o['infeasible'], $outcomes))) > 1;
        $merged = true;
        while ($merged && !$mixedFeasibility) {
            $merged = false;
            foreach ($blocks as $a => $seatsA) {
                foreach ($blocks as $b => $seatsB) {
                    if ($b <= $a) {
                        continue;
                    }
                    if ($distinct([...$seatsA, ...$seatsB]) !== $distinct($seatsA) * $distinct($seatsB)) {
                        $blocks[$a] = [...$seatsA, ...$seatsB];
                        sort($blocks[$a]);
                        unset($blocks[$b]);
                        $blocks = array_values($blocks);
                        $merged = true;
                        continue 3;
                    }
                }
            }
        }
        $product = array_product(array_map($distinct, $blocks));
        if ($mixedFeasibility || count($blocks) < 2 || $product !== count($outcomes)) {
            $blocks = [$open];
        }

        $ties = [];
        foreach ($blocks as $seats) {
            /** @var array<string,list<Scenario>> $byValue */
            $byValue = [];
            foreach ($outcomes as $o) {
                $byValue[$project($o, $seats)][] = $o;
            }
            if (count($byValue) > self::MAX_SCENARIOS) {
                return null;
            }
            $inSeats = [];
            foreach ($outcomes as $o) {
                foreach ($seats as $s) {
                    if (isset($o['order'][$s])) {
                        $inSeats[$o['order'][$s]] = true;
                    }
                }
            }

            $options = [];
            foreach ($byValue as $value => $group) {
                $o = $group[0];
                $occupants = [];
                foreach ($seats as $s) {
                    if (isset($o['order'][$s])) {
                        $occupants[$s + 1] = $o['order'][$s];
                    }
                }
                $out = array_values(array_diff(array_map('strval', array_keys($inSeats)), $o['order']));
                usort($out, fn (string $a, string $b): int => $this->index[$a] <=> $this->index[$b]);
                $options[] = [
                    'when' => $this->condition($group, $outcomes, static fn (array $other): bool => $project($other, $seats) !== (string) $value),
                    'seats' => $occupants,
                    'out' => $out,
                    'infeasible' => $o['infeasible'],
                ];
            }
            // Options with a plain condition first; the rest after them.
            usort($options, static fn (array $a, array $b): int => ($a['when'] === []) <=> ($b['when'] === []));
            $ties[] = ['seats' => array_map(static fn (int $s): int => $s + 1, $seats), 'options' => $options];
        }

        return $ties;
    }

    /**
     * The condition leading to a group of slates, as "ahead of" statements:
     * the tie-order relations every resolution in the group shares, kept
     * only if EXACT -- every branch leading to any other slate breaks at
     * least one of them. Empty when no such plain condition exists.
     *
     * @param list<Scenario> $group
     * @param list<Scenario> $outcomes
     * @param callable(Scenario):bool $isOther
     * @return list<array{ahead:string,behind:list<string>}>
     */
    private function condition(array $group, array $outcomes, callable $isOther): array
    {
        $common = null;
        foreach ($group as $g) {
            $common = $common === null ? $g['forced'] : array_intersect_key($common, $g['forced']);
        }
        if ($common === null || $common === []) {
            return [];
        }
        foreach ($outcomes as $other) {
            if (!$isOther($other)) {
                continue;
            }
            foreach ($other['prefixes'] as $prefix) {
                $breaks = false;
                foreach ($common as [$x, $y]) {
                    if ($this->forces($prefix, $y, $x)) {
                        $breaks = true;
                        break;
                    }
                }
                if (!$breaks) {
                    return [];
                }
            }
        }

        return $this->describe($common);
    }

    /**
     * Walk the tie resolutions as a decision tree, branching only while the
     * slate is still open (the memoised DP answers that at every node).
     *
     * @param State $state
     * @param list<array{0:int,1:int,2:State,3:string}> $path placements so far: candidate, position, state before, placed mask before
     * @param list<array{prefix:list<int>,order:list<string>,infeasible:bool}> $leaves
     */
    private function branch(string $rest, int $placed, array $state, array $path, array &$leaves): void
    {
        $summary = $this->solve($rest, $placed, $state);
        if (count($summary) === 1 && !reset($summary)[1]) {
            $hyp = (string) array_key_first($summary);
            $slate = $summary[$hyp][0];
            foreach ($path as [$i, $at, $before, $mask]) {
                $seat = $this->seatFor($i, $at, $before, $mask, $hyp);
                if ($seat !== null) {
                    $slate = substr_replace($slate, $this->encode($i), $seat * $this->w, $this->w);
                }
            }
            if (count($leaves) >= self::MAX_BRANCHES) {
                throw new QuotaTooComplex();
            }
            $order = [];
            foreach (str_split($slate, $this->w) as $code) {
                if ($code !== $this->unset) {
                    $order[] = $this->roster[$this->decode($code)];
                }
            }
            $leaves[] = [
                'prefix' => array_map(static fn (array $step): int => $step[0], $path),
                'order' => $order,
                'infeasible' => $this->hypMeta[$hyp]['infeasible'],
            ];

            return;
        }

        $placedMask = strtr($rest, '01', '10');
        for ($i = 0; $i < $this->n; $i++) {
            if ($rest[$i] !== '1' || str_contains($this->pred[$i] & $rest, '1')) {
                continue;
            }
            $childRest = $rest;
            $childRest[$i] = '0';
            $this->branch($childRest, $placed + 1, $this->advance($i, $placed, $state), [...$path, [$i, $placed, $state, $placedMask]], $leaves);
        }
    }

    /**
     * Every "x before y" between two TIED candidates (incomparable by the
     * votes) that holds in all resolutions starting with `$prefix`.
     *
     * @param list<int> $prefix
     * @return array<string,array{0:int,1:int}> "x,y" => [x, y]
     */
    private function forcedRelations(array $prefix): array
    {
        $at = array_flip($prefix);
        $relations = [];
        foreach ($prefix as $x) {
            for ($y = 0; $y < $this->n; $y++) {
                if ($y !== $x && $this->pred[$x][$y] === '0' && $this->succ[$x][$y] === '0'
                    && (!isset($at[$y]) || $at[$x] < $at[$y])) {
                    $relations["{$x},{$y}"] = [$x, $y];
                }
            }
        }

        return $relations;
    }

    /**
     * Does every resolution starting with `$prefix` place `$x` before `$y`?
     *
     * @param list<int> $prefix
     */
    private function forces(array $prefix, int $x, int $y): bool
    {
        $ax = array_search($x, $prefix, true);
        if ($ax === false) {
            return $this->succ[$x][$y] === '1';
        }
        $ay = array_search($y, $prefix, true);

        return $ay === false || $ax < $ay;
    }

    /**
     * The fewest "ahead of" statements carrying a relation set: drop any
     * pair already implied through a third candidate (by the votes or by
     * another statement), then group by the candidate placed ahead.
     *
     * @param array<string,array{0:int,1:int}> $relations
     * @return list<array{ahead:string,behind:list<string>}>
     */
    private function describe(array $relations): array
    {
        $before = fn (int $a, int $b): bool => $this->succ[$a][$b] === '1' || isset($relations["{$a},{$b}"]);
        $kept = [];
        foreach ($relations as [$x, $y]) {
            $implied = false;
            for ($z = 0; $z < $this->n && !$implied; $z++) {
                $implied = $z !== $x && $z !== $y && $before($x, $z) && $before($z, $y);
            }
            if (!$implied) {
                $kept[$x][] = $y;
            }
        }
        ksort($kept);

        $when = [];
        foreach ($kept as $x => $ys) {
            sort($ys);
            $when[] = [
                'ahead' => (string) $this->roster[$x],
                'behind' => array_map(fn (int $y): string => (string) $this->roster[$y], $ys),
            ];
        }

        return $when;
    }

    /**
     * Predecessor / successor masks of the strict partial order.
     *
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     */
    private function buildOrder(array $bands): void
    {
        $bandOf = [];
        $beatsInBand = [];
        foreach ($bands as $bi => $band) {
            foreach ($band['candidates'] as $c) {
                $bandOf[$c] = $bi;
            }
            foreach ($band['internal_constraints'] as $con) {
                $beatsInBand[$con['winner']][$con['loser']] = true;
            }
        }

        $beats = static function (int $i, string $a, int $j, string $b) use ($bandOf, $beatsInBand): bool {
            if ($i === $j) {
                return false;
            }
            $band = $bandOf[$a] ?? null;

            return ($band !== null && $band === ($bandOf[$b] ?? null))
                ? ($beatsInBand[$a][$b] ?? false)
                : $i < $j;
        };

        foreach ($this->roster as $i => $a) {
            $succ = '';
            $pred = '';
            foreach ($this->roster as $j => $b) {
                $succ .= $beats($i, $a, $j, $b) ? '1' : '0';
                $pred .= $beats($j, $b, $i, $a) ? '1' : '0';
            }
            $this->succ[] = $succ;
            $this->pred[] = $pred;
        }
    }

    /**
     * Pick the automaton. Min/max feasibility does not depend on how ties
     * resolve (it only compares category totals with the seat count), so an
     * infeasible or inapplicable min/max quota is settled here and the
     * slate is simply the natural top in every resolution.
     */
    private function configure(): void
    {
        foreach ($this->roster as $i => $c) {
            $cat = $this->categories[$c] ?? null;
            $this->categoryOf[$i] = $cat;
            if ($cat !== null) {
                $this->categoryMask[$cat] ??= str_repeat('0', $this->n);
                $this->categoryMask[$cat][$i] = '1';
            }
        }

        if ($this->k === 0) {
            $this->mode = 'natural';

            return;
        }

        if ($this->quota['type'] === 'alternate') {
            $this->mode = 'alternate';

            return;
        }

        $category = $this->quota['category'];
        $count = $this->quota['count'];
        $isMin = $this->quota['type'] === 'min';

        if (!in_array($category, $this->categoryOf, true)) {
            $this->mode = 'natural';
            $this->naturalWarning = __('components.orderedlist.quota_warn_category_absent', ['category' => $category]);

            return;
        }

        $inCategory = 0;
        foreach ($this->roster as $i => $c) {
            $in = $this->categoryOf[$i] === $category;
            $inCategory += $in ? 1 : 0;
            // min: non-members may leave the top; max: members may.
            $this->leaveSide[$i] = $isMin ? !$in : $in;
        }

        $feasible = $isMin
            ? $inCategory >= $count && $this->k >= $count
            : ($this->n - $inCategory) >= $this->k - $count;
        if (!$feasible) {
            $this->mode = 'natural';
            $this->naturalInfeasible = true;
            $this->naturalWarning = $isMin
                ? __('components.orderedlist.quota_warn_min_infeasible', ['category' => $category])
                : __('components.orderedlist.quota_warn_max_infeasible', ['category' => $category]);

            return;
        }

        $this->mode = 'minmax';
        // min: at most k-count non-members keep their seat; max: at most count members.
        $this->keepBound = $isMin ? $this->k - $count : $count;
    }

    /**
     * Summary of every slate produced by completing the current prefix,
     * over every linear extension of the unplaced candidates `$rest`.
     *
     * @param string $rest '1' per still-unplaced candidate
     * @param State $state
     * @return Summary
     */
    private function solve(string $rest, int $placed, array $state): array
    {
        $key = $rest . '|' . $state['lv'] . '|' . $state['e'] . '|' . $this->field($state['g1']) . $this->field($state['g2']) . $this->field($state['inf']);
        if (isset($this->memo[$key])) {
            return $this->unpack($this->memo[$key]);
        }
        if (++$this->nodes > $this->maxNodes || (($this->nodes & 63) === 0 && memory_get_usage() > $this->memoryCeiling)) {
            throw new QuotaTooComplex();
        }

        if ($this->complete($rest, $placed, $state)) {
            $hyp = $this->hypothesis($state);
            $summary = [$hyp => [
                str_repeat($this->unset, $this->k),
                false,
                str_repeat($this->unset, $this->k),
                str_repeat('0', $this->n),
                str_repeat('0', $this->n),
            ]];

            $this->memo[$key] = $this->pack($summary);

            return $summary;
        }

        // Candidates that can legally come next, grouped into classes the
        // automaton cannot tell apart from here on.
        /** @var array<string,list<int>> $classes */
        $classes = [];
        for ($i = 0; $i < $this->n; $i++) {
            if ($rest[$i] !== '1' || str_contains($this->pred[$i] & $rest, '1')) {
                continue;
            }
            $classKey = $this->observed($i, $placed, $state) . '|' . ($this->succ[$i] & $rest);
            $classes[$classKey][] = $i;
        }

        $placedMask = strtr($rest, '01', '10');
        $out = [];
        foreach ($classes as $class) {
            $rep = $class[0];
            $childRest = $rest;
            $childRest[$rep] = '0';
            $child = $this->solve($childRest, $placed + 1, $this->advance($rep, $placed, $state));
            $branch = $this->place($rep, $placed, $state, $placedMask, $child);
            $out = $this->merge($out, $branch);
            foreach (array_slice($class, 1) as $member) {
                $out = $this->merge($out, $this->swap($branch, $rep, $member));
            }
        }

        $this->memo[$key] = $this->pack($out);

        return $out;
    }

    /**
     * One string per memoised state keeps memory at a few hundred bytes per
     * state (an array of arrays costs several times that).
     *
     * @param Summary $summary
     */
    private function pack(array $summary): string
    {
        $packed = '';
        foreach ($summary as $hyp => $h) {
            $hyp = (string) $hyp;
            $packed .= pack('N', strlen($hyp)) . $hyp . ($h[1] ? '1' : '0') . $h[0] . $h[2] . $h[3] . $h[4];
        }

        return $packed;
    }

    /** @return Summary */
    private function unpack(string $packed): array
    {
        $summary = [];
        $at = 0;
        $length = strlen($packed);
        while ($at < $length) {
            /** @var array{1:int} $len */
            $len = unpack('N', $packed, $at);
            $hyp = substr($packed, $at + 4, $len[1]);
            $at += 4 + $len[1];
            $seats = $this->k * $this->w;
            $summary[$hyp] = [
                substr($packed, $at + 1, $seats),
                $packed[$at] === '1',
                substr($packed, $at + 1 + $seats, $seats),
                substr($packed, $at + 1 + 2 * $seats, $this->n),
                substr($packed, $at + 1 + 2 * $seats + $this->n, $this->n),
            ];
            $at += 1 + 2 * $seats + 2 * $this->n;
        }

        return $summary;
    }

    /**
     * Has every slate seat been decided? The rest of the order is then
     * irrelevant.
     *
     * @param State $state
     */
    private function complete(string $rest, int $placed, array $state): bool
    {
        if ($placed < $this->k) {
            return false;
        }
        if ($this->mode === 'natural' || $state['inf'] !== null) {
            return true;
        }
        if ($this->mode === 'minmax') {
            return $state['e'] >= $this->need($state);
        }

        /** @var string $g1 */
        $g1 = $state['g1'];
        /** @var string $g2 */
        $g2 = $state['g2'];
        $pattern = $this->pattern($g1, $g2);
        $placedMask = strtr($rest, '01', '10');

        return substr_count($placedMask & $this->categoryMask[$g1], '1') >= count($pattern[$g1])
            && substr_count($placedMask & $this->categoryMask[$g2], '1') >= count($pattern[$g2]);
    }

    /**
     * What the automaton can observe about candidate `$i`, now and in any
     * later state. Candidates with the same value (and the same relations
     * to everyone unplaced) are interchangeable.
     *
     * @param State $state
     */
    private function observed(int $i, int $placed, array $state): string
    {
        if ($this->mode === 'natural' || $state['inf'] !== null) {
            return '';
        }
        if ($this->mode === 'minmax') {
            return $this->leaveSide[$i] ? 'L' : 'E';
        }

        $cat = $this->categoryOf[$i];
        if ($state['g1'] === null || $state['g2'] === null) {
            return 'c:' . ($cat ?? "\0");
        }

        return $cat === $state['g1'] ? 'a' : ($cat === $state['g2'] ? 'b' : 'o');
    }

    /**
     * Automaton transition: read candidate `$i` as the next in the order.
     *
     * @param State $state
     * @return State
     */
    private function advance(int $i, int $placed, array $state): array
    {
        if ($this->mode === 'minmax') {
            if ($placed < $this->k) {
                $state['lv'] += $this->leaveSide[$i] ? 1 : 0;
            } elseif (!$this->leaveSide[$i] && $state['e'] < $this->need($state)) {
                $state['e']++;
            }

            return $state;
        }
        if ($this->mode === 'natural' || $state['inf'] !== null || $placed >= $this->k) {
            return $state;
        }

        // Alternation, still inside the natural top (D13/D13.1 group rules).
        $cat = $this->categoryOf[$i];
        if ($placed === 0) {
            if ($cat === null) {
                $state['inf'] = 'components.orderedlist.alternate_warn_no_start_group';

                return $state;
            }
            $state['g1'] = $cat;
        } elseif ($cat === null) {
            $state['inf'] = 'components.orderedlist.alternate_warn_extra_category';

            return $state;
        } elseif ($cat !== $state['g1']) {
            if ($state['g2'] === null) {
                $state['g2'] = $cat;
            } elseif ($cat !== $state['g2']) {
                $state['inf'] = 'components.orderedlist.alternate_warn_extra_category';

                return $state;
            }
        }

        if ($placed + 1 === $this->k) {
            // The top is complete: settle the second group and the pools.
            if ($state['g2'] === null) {
                $others = array_values(array_unique(array_filter(
                    $this->categoryOf,
                    static fn (?string $c): bool => $c !== null && $c !== $state['g1']
                )));
                if ($others === []) {
                    $state['inf'] = 'components.orderedlist.alternate_warn_one_category';

                    return $state;
                }
                if (count($others) >= 2) {
                    $state['inf'] = 'components.orderedlist.alternate_warn_ambiguous_second_group';

                    return $state;
                }
                $state['g2'] = $others[0];
            }
            /** @var string $g1 */
            $g1 = $state['g1'];
            if (substr_count($this->categoryMask[$g1], '1') + substr_count($this->categoryMask[$state['g2']], '1') < $this->k) {
                $state['inf'] = 'components.orderedlist.alternate_warn_not_enough_candidates';
            }
        }

        return $state;
    }

    /**
     * Prepend "candidate `$i` read at position `$placed`" to every slate of
     * a child summary. Which seat it takes depends on the child's outcome
     * (hypothesis): an alternation that turns out infeasible keeps the
     * natural top instead of the zipper.
     *
     * @param State $state the state BEFORE reading `$i`
     * @param Summary $child
     * @return Summary
     */
    private function place(int $i, int $placed, array $state, string $placedMask, array $child): array
    {
        $code = $this->encode($i);
        foreach ($child as $hyp => $h) {
            $seat = $this->seatFor($i, $placed, $state, $placedMask, $hyp);
            if ($seat === null) {
                continue;
            }
            $h[0] = substr_replace($h[0], $code, $seat * $this->w, $this->w);
            $h[2] = substr_replace($h[2], $code, $seat * $this->w, $this->w);
            $h[3][$i] = '1';
            $h[4][$i] = '1';
            $child[$hyp] = $h;
        }

        return $child;
    }

    /**
     * The slate seat (0-based) candidate `$i` takes when read at position
     * `$placed`, or null if it is not seated.
     *
     * @param State $state the state BEFORE reading `$i`
     */
    private function seatFor(int $i, int $placed, array $state, string $placedMask, string $hyp): ?int
    {
        if ($hyp[0] === 'n' || $hyp[0] === 'i') {
            return $placed < $this->k ? $placed : null;
        }

        if ($hyp[0] === 'm') {
            if ($placed < $this->k) {
                if ($this->leaveSide[$i] && $state['lv'] + 1 > $this->keepBound) {
                    return null; // among the worst-placed of the over-represented side
                }

                return $placed - max(0, $state['lv'] - $this->keepBound);
            }
            $need = $this->need($state);
            if (!$this->leaveSide[$i] && $state['e'] < $need) {
                return $this->k - $need + $state['e'];
            }

            return null;
        }

        // 'z' . g1 . "\0" . g2: the zipper.
        [$g1, $g2] = explode("\0", substr($hyp, 1), 2);
        $cat = $this->categoryOf[$i];
        if ($cat !== $g1 && $cat !== $g2) {
            return null;
        }
        $rank = substr_count($placedMask & $this->categoryMask[$cat], '1');

        return $this->pattern($g1, $g2)[$cat][$rank] ?? null;
    }

    /**
     * Min/max: how many below-cut candidates must enter (known once the top
     * is complete; the leavers are exactly the leave-side overflow).
     *
     * @param State $state
     */
    private function need(array $state): int
    {
        return max(0, $state['lv'] - $this->keepBound);
    }

    /**
     * Seats taken by each alternation group: alternate starting with g1;
     * once a group runs out the other fills the remaining seats.
     *
     * @return array<string,list<int>>
     */
    private function pattern(string $g1, string $g2): array
    {
        $key = $g1 . "\0" . $g2;
        if (!isset($this->patterns[$key])) {
            $a = substr_count($this->categoryMask[$g1], '1');
            $b = substr_count($this->categoryMask[$g2], '1');
            $seats = [$g1 => [], $g2 => []];
            $usedA = 0;
            $usedB = 0;
            for ($s = 0; $s < $this->k; $s++) {
                $fromStart = $s % 2 === 0 ? $usedA < $a : $usedB >= $b;
                if ($fromStart) {
                    $seats[$g1][] = $s;
                    $usedA++;
                } else {
                    $seats[$g2][] = $s;
                    $usedB++;
                }
            }
            $this->patterns[$key] = $seats;
        }

        return $this->patterns[$key];
    }

    /**
     * The outcome class of a completed prefix.
     *
     * @param State $state
     */
    private function hypothesis(array $state): string
    {
        if ($this->mode === 'natural') {
            $this->hypMeta['n'] ??= [
                'infeasible' => $this->naturalInfeasible,
                'warnings' => $this->naturalWarning === null ? [] : [$this->naturalWarning],
            ];

            return 'n';
        }
        if ($this->mode === 'minmax') {
            $this->hypMeta['m'] ??= ['infeasible' => false, 'warnings' => []];

            return 'm';
        }
        if ($state['inf'] !== null) {
            $hyp = 'i' . $state['inf'];
            $this->hypMeta[$hyp] ??= ['infeasible' => true, 'warnings' => [__($state['inf'])]];

            return $hyp;
        }
        $hyp = 'z' . $state['g1'] . "\0" . $state['g2'];
        $this->hypMeta[$hyp] ??= ['infeasible' => false, 'warnings' => []];

        return $hyp;
    }

    /**
     * @param Summary $a
     * @param Summary $b
     * @return Summary
     */
    private function merge(array $a, array $b): array
    {
        foreach ($b as $hyp => $h) {
            $a[$hyp] = isset($a[$hyp]) ? $this->mergeHyp($a[$hyp], $h) : $h;
        }

        return $a;
    }

    /**
     * @param Hyp $a
     * @param Hyp $b
     * @return Hyp
     */
    private function mergeHyp(array $a, array $b): array
    {
        $agree = $a[2];
        if ($agree !== $b[2]) {
            for ($s = 0, $w = $this->w; $s < $this->k; $s++) {
                if (substr($agree, $s * $w, $w) !== substr($b[2], $s * $w, $w)) {
                    $agree = substr_replace($agree, $this->mixed, $s * $w, $w);
                }
            }
        }

        return [$a[0], $a[1] || $b[1] || $a[0] !== $b[0], $agree, $a[3] & $b[3], $a[4] | $b[4]];
    }

    /**
     * Rename two interchangeable candidates throughout a summary.
     *
     * @param Summary $summary
     * @return Summary
     */
    private function swap(array $summary, int $x, int $y): array
    {
        foreach ($summary as $hyp => $h) {
            foreach ([3, 4] as $set) {
                [$h[$set][$x], $h[$set][$y]] = [$h[$set][$y], $h[$set][$x]];
            }
            $summary[$hyp] = [$this->rename($h[0], $x, $y), $h[1], $this->rename($h[2], $x, $y), $h[3], $h[4]];
        }

        return $summary;
    }

    /** Swap two candidates' codes in a packed seat string (aligned). */
    private function rename(string $seats, int $x, int $y): string
    {
        $cx = $this->encode($x);
        $cy = $this->encode($y);
        if ($this->w === 1) {
            return strtr($seats, [$cx => $cy, $cy => $cx]);
        }
        $out = '';
        foreach (str_split($seats, 2) as $code) {
            $out .= $code === $cx ? $cy : ($code === $cy ? $cx : $code);
        }

        return $out;
    }

    private function encode(int $i): string
    {
        return $this->w === 1 ? chr($i) : pack('n', $i);
    }

    private function decode(string $code): int
    {
        if ($this->w === 1) {
            return ord($code);
        }
        /** @var array{1:int} $value */
        $value = unpack('n', $code);

        return $value[1];
    }

    /** Length-prefixed memo-key field (category names are arbitrary strings). */
    private function field(?string $value): string
    {
        return $value === null ? '-|' : strlen($value) . ':' . $value . '|';
    }

    /**
     * Absolute memory_usage() bound for the memo: what the request already
     * uses plus a fixed headroom, kept well inside memory_limit.
     */
    private function memoryCeiling(): int
    {
        $now = memory_get_usage();
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') {
            return $now + self::MEMORY_HEADROOM;
        }
        $bytes = (int) $limit;
        $unit = strtolower(substr($limit, -1));
        $bytes *= match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return $now + max(0, min(self::MEMORY_HEADROOM, intdiv(($bytes - $now) * 6, 10)));
    }

    /**
     * Fold every outcome class into what all resolutions agree on.
     *
     * @param Summary $summary
     * @return Result
     */
    private function aggregate(array $summary): array
    {
        $first = null;
        $multi = false;
        $agree = '';
        $inter = '';
        $union = '';
        $allInfeasible = true;
        $anyInfeasible = false;

        foreach ($summary as $hyp => $h) {
            $meta = $this->hypMeta[$hyp];
            foreach ($meta['warnings'] as $w) {
                if (!in_array($w, $this->warnings, true)) {
                    $this->warnings[] = $w;
                }
            }
            $allInfeasible = $allInfeasible && $meta['infeasible'];
            $anyInfeasible = $anyInfeasible || $meta['infeasible'];

            if ($first === null) {
                [$first, $multi, $agree, $inter, $union] = $h;
                continue;
            }
            [$first, $multi, $agree, $inter, $union] = $this->mergeHyp([$first, $multi, $agree, $inter, $union], $h);
        }
        /** @var string $first */

        if ($multi) {
            $this->warnings[] = __('components.orderedlist.quota_warn_surfaced');
        }

        $positions = [];
        $prefix = [];
        $prefixOpen = true;
        for ($s = 0; $s < $this->k; $s++) {
            $code = substr($agree, $s * $this->w, $this->w);
            if ($code === $this->mixed || $code === $this->unset) {
                $prefixOpen = false;
                continue;
            }
            $candidate = $this->roster[$this->decode($code)];
            $positions[$candidate] = $s + 1;
            if ($prefixOpen) {
                $prefix[] = $candidate;
            }
        }

        $seated = [];
        $contested = [];
        for ($i = 0; $i < $this->n; $i++) {
            if ($inter[$i] === '1') {
                $seated[] = $this->roster[$i];
            } elseif ($union[$i] === '1') {
                $contested[] = $this->roster[$i];
            }
        }
        $sortKey = fn (string $c): array => [$positions[$c] ?? PHP_INT_MAX, $this->index[$c]];
        usort($seated, static fn (string $a, string $b): int => $sortKey($a) <=> $sortKey($b));

        $order = $multi
            ? $prefix
            : array_map(fn (string $code): string => $this->roster[$this->decode($code)], str_split($first, $this->w));

        return $this->shape(
            $order,
            $this->diff($seated, $positions),
            $allInfeasible,
            $anyInfeasible && !$allInfeasible,
            $multi,
            false,
            $seated,
            $contested,
            $positions,
        );
    }

    /**
     * Exact answers that need no search. Null when the case needs the DP.
     *
     * @return Result|null
     */
    private function closedForm(): ?array
    {
        if (!$this->closedForms) {
            return null;
        }
        if ($this->mode === 'natural') {
            return $this->naturalResult($this->naturalInfeasible, $this->naturalWarning === null ? [] : [$this->naturalWarning]);
        }
        if ($this->mode === 'alternate') {
            return $this->alternationResult();
        }

        return null;
    }

    /**
     * The natural top in every resolution: exactly the resolver's position
     * intervals (best/worst position over all linear extensions). Seated:
     * worst position within the seats; contested: only the best one is;
     * certain seat: best = worst.
     *
     * @param list<string> $warnings
     * @return Result
     */
    private function naturalResult(bool $infeasible, array $warnings): array
    {
        $seated = [];
        $contested = [];
        $positions = [];
        foreach ($this->ranking as $i => $entry) {
            $c = $this->roster[$i];
            if ($entry['worst_pos'] <= $this->k) {
                $seated[] = $c;
            } elseif ($entry['best_pos'] <= $this->k) {
                $contested[] = $c;
            }
            if ($entry['best_pos'] === $entry['worst_pos'] && $entry['best_pos'] <= $this->k) {
                $positions[$c] = $entry['best_pos'];
            }
        }

        return $this->closedResult($seated, $contested, $positions, $infeasible, $warnings);
    }

    /**
     * Alternation whose two groups (and feasibility) are the same in every
     * resolution -- the usual case. The zipper's seat s then takes the r-th
     * best member of its group, so each candidate's possible seats follow
     * exactly from its rank interval INSIDE its group (one plus the group
     * members that beat it, up to one plus those it does not beat). If the
     * rule is infeasible in every resolution, the natural top stands. If
     * the groups or feasibility can differ between resolutions, null (DP).
     *
     * @return Result|null
     */
    private function alternationResult(): ?array
    {
        $infeasible = fn (string $key): array => $this->naturalResult(true, [__($key)]);

        $always = $this->alternationAlwaysInfeasible();
        if ($always !== null) {
            return $infeasible($always);
        }

        // The start group is fixed when everyone who could be #1 shares it.
        $leaders = [];
        foreach ($this->ranking as $i => $entry) {
            if ($entry['best_pos'] === 1) {
                $leaders[$this->categoryOf[$i] ?? "\0"] = true;
            }
        }
        if (count($leaders) !== 1) {
            return null; // the start group itself hinges on a tie
        }
        $g1 = (string) array_key_first($leaders);
        if ($g1 === "\0") {
            return $infeasible('components.orderedlist.alternate_warn_no_start_group');
        }

        $sureCats = [];
        $maybeOthers = [];
        $sureOthers = [];
        foreach ($this->ranking as $i => $entry) {
            if ($entry['best_pos'] > $this->k) {
                continue;
            }
            $sure = $entry['worst_pos'] <= $this->k;
            $cat = $this->categoryOf[$i];
            if ($cat === null) {
                if ($sure) {
                    return $infeasible('components.orderedlist.alternate_warn_extra_category');
                }

                return null;
            }
            if ($sure) {
                $sureCats[$cat] = true;
            }
            if ($cat !== $g1) {
                $maybeOthers[$cat] = true;
                if ($sure) {
                    $sureOthers[$cat] = true;
                }
            }
        }
        if (count($sureCats) >= 3) {
            return $infeasible('components.orderedlist.alternate_warn_extra_category');
        }
        if (count($maybeOthers) >= 2) {
            return null;
        }

        $rosterOthers = array_values(array_unique(array_filter(
            $this->categoryOf,
            static fn (?string $c): bool => $c !== null && $c !== $g1
        )));
        if ($maybeOthers === []) {
            if ($rosterOthers === []) {
                return $infeasible('components.orderedlist.alternate_warn_one_category');
            }
            if (count($rosterOthers) >= 2) {
                return $infeasible('components.orderedlist.alternate_warn_ambiguous_second_group');
            }
            $g2 = $rosterOthers[0];
        } else {
            $g2 = (string) array_key_first($maybeOthers);
            if ($sureOthers === [] && $rosterOthers !== [$g2]) {
                return null; // an all-g1 top would derive another (or no) second group
            }
        }

        if (substr_count($this->categoryMask[$g1], '1') + substr_count($this->categoryMask[$g2], '1') < $this->k) {
            return $infeasible('components.orderedlist.alternate_warn_not_enough_candidates');
        }

        $pattern = $this->pattern($g1, $g2);
        $seated = [];
        $contested = [];
        $positions = [];
        foreach ([$g1, $g2] as $group) {
            $mask = $this->categoryMask[$group];
            $members = substr_count($mask, '1');
            $seatsOfGroup = $pattern[$group];
            for ($i = 0; $i < $this->n; $i++) {
                if ($mask[$i] !== '1') {
                    continue;
                }
                $minRank = 1 + substr_count($this->pred[$i] & $mask, '1');
                $maxRank = $members - substr_count($this->succ[$i] & $mask, '1');
                $c = $this->roster[$i];
                if ($maxRank <= count($seatsOfGroup)) {
                    $seated[] = $c;
                } elseif ($minRank <= count($seatsOfGroup)) {
                    $contested[] = $c;
                }
                if ($minRank === $maxRank && $minRank <= count($seatsOfGroup)) {
                    $positions[$c] = $seatsOfGroup[$minRank - 1] + 1;
                }
            }
        }
        usort($contested, fn (string $a, string $b): int => $this->index[$a] <=> $this->index[$b]);

        return $this->closedResult($seated, $contested, $positions, false, []);
    }

    /**
     * @param list<string> $seated
     * @param list<string> $contested
     * @param array<string,int> $positions
     * @param list<string> $warnings
     * @return Result
     */
    private function closedResult(array $seated, array $contested, array $positions, bool $infeasible, array $warnings): array
    {
        $provisional = count($positions) < $this->k;
        foreach ($warnings as $w) {
            $this->warnings[] = $w;
        }
        if ($provisional) {
            $this->warnings[] = __('components.orderedlist.quota_warn_surfaced');
        }

        asort($positions);
        $bySeat = array_flip($positions);
        $order = [];
        for ($s = 1; isset($bySeat[$s]); $s++) {
            $order[] = (string) $bySeat[$s];
        }
        $sortKey = fn (string $c): array => [$positions[$c] ?? PHP_INT_MAX, $this->index[$c]];
        usort($seated, static fn (string $a, string $b): int => $sortKey($a) <=> $sortKey($b));

        return $this->shape($order, $this->diff($seated, $positions), $infeasible, false, $provisional, false, $seated, $contested, $positions);
    }

    /**
     * Beyond the cap: a SOUND partial answer, never a guess. Seated only
     * where a cheap argument proves it for every resolution; contested is a
     * superset of everyone who could take a seat; no certain seat numbers.
     *
     *   - natural top (min/max inapplicable or infeasible): exactly the
     *     resolver's elected / contested statuses;
     *   - min/max: a surely-elected candidate on the side that never leaves
     *     stays; one on the leaving side stays when even counting every
     *     same-side candidate that could precede it, it is within the
     *     candidates who keep their seat. Anyone who could be in the top or
     *     could enter from below may be seated;
     *   - alternation: nothing is certain; anyone who could be in the top,
     *     or belongs to a category the zipper could draw from, may be seated.
     *
     * @return Result
     */
    private function fallback(): array
    {
        $infeasible = $this->naturalInfeasible
            || (($this->mode ?? null) === 'alternate' && $this->alternationAlwaysInfeasible() !== null);
        $seated = [];
        $possible = [];
        foreach ($this->ranking as $i => $entry) {
            $c = $this->roster[$i];
            $surelyTop = $entry['status'] === 'elected';
            $maybeTop = $entry['best_pos'] <= $this->k;
            $category = $this->categories[$c] ?? null;

            if (!isset($this->mode)) {
                $possible[] = $c;
                continue;
            }

            if ($this->mode === 'natural' || $infeasible) {
                if ($surelyTop) {
                    $seated[] = $c;
                } elseif ($maybeTop) {
                    $possible[] = $c;
                }
                continue;
            }

            if ($this->mode === 'minmax') {
                if ($surelyTop && (!$this->leaveSide[$i] || $this->maxLeaveRank($i) <= $this->keepBound)) {
                    $seated[] = $c;
                } elseif ($maybeTop || !$this->leaveSide[$i]) {
                    $possible[] = $c;
                }
                continue;
            }

            if ($maybeTop || $category !== null) {
                $possible[] = $c;
            }
        }

        return $this->shape([], [], $infeasible, false, true, true, $seated, $possible, []);
    }

    /**
     * An alternation infeasibility that holds in EVERY tie resolution,
     * whoever ends up #1 (an untagged #1 is infeasible on its own), as a
     * warning key; null when some resolution may be feasible.
     */
    private function alternationAlwaysInfeasible(): ?string
    {
        $sizes = [];
        foreach ($this->categoryMask as $mask) {
            $sizes[] = substr_count($mask, '1');
        }
        if (count($sizes) < 2) {
            return 'components.orderedlist.alternate_warn_one_category';
        }

        $sureCats = [];
        foreach ($this->ranking as $i => $entry) {
            if ($entry['worst_pos'] > $this->k) {
                continue;
            }
            $cat = $this->categoryOf[$i];
            if ($cat === null) {
                return 'components.orderedlist.alternate_warn_extra_category';
            }
            $sureCats[$cat] = true;
        }
        if (count($sureCats) >= 3) {
            return 'components.orderedlist.alternate_warn_extra_category';
        }

        rsort($sizes);
        if ($sizes[0] + $sizes[1] < $this->k) {
            return 'components.orderedlist.alternate_warn_not_enough_candidates';
        }

        return null;
    }

    /**
     * Worst possible rank of leave-side candidate `$i` among the leave-side
     * candidates of the top: one plus every other leave-side candidate it
     * does not beat (any of them could be placed before it).
     */
    private function maxLeaveRank(int $i): int
    {
        $rank = 1;
        foreach ($this->leaveSide as $j => $leaves) {
            if ($leaves && $j !== $i && $this->succ[$i][$j] !== '1') {
                $rank++;
            }
        }

        return $rank;
    }

    /**
     * Audit trail (display only): every certainly-seated candidate who is
     * there because of the quota rather than the votes alone -- entered
     * from below the cut (`below_cut`) or from a contested cut
     * (`contested`) -- plus, for alternation, a surely-elected candidate
     * whose certain seat differs from their natural place
     * (`natural:<rank>`).
     *
     * @param list<string> $seated
     * @param array<string,int> $positions
     * @return list<array{candidate:string,from:string,reason:string}>
     */
    private function diff(array $seated, array $positions): array
    {
        $type = $this->quota['type'];
        $reason = $type === 'alternate' ? 'alternate' : "{$type}_quota:{$this->quota['category']}";

        $diff = [];
        foreach ($seated as $candidate) {
            $idx = $this->index[$candidate];
            $status = $this->ranking[$idx]['status'];
            if ($status === 'excluded') {
                $diff[] = ['candidate' => $candidate, 'from' => 'below_cut', 'reason' => $reason];
            } elseif ($status === 'contested') {
                $diff[] = ['candidate' => $candidate, 'from' => 'contested', 'reason' => $reason];
            } elseif ($type === 'alternate' && isset($positions[$candidate]) && $positions[$candidate] !== $idx + 1) {
                $diff[] = ['candidate' => $candidate, 'from' => 'natural:' . ($idx + 1), 'reason' => $reason];
            }
        }

        return $diff;
    }

    /**
     * @param list<string> $order
     * @param list<array{candidate:string,from:string,reason:string}> $diff
     * @param list<string> $seated
     * @param list<string> $contested
     * @param array<string,int> $positions
     * @return Result
     */
    private function shape(array $order, array $diff, bool $infeasible, bool $partlyInfeasible, bool $provisional, bool $tooComplex, array $seated, array $contested, array $positions): array
    {
        return [
            'order' => $order,
            'diff' => $diff,
            'infeasible' => $infeasible,
            'partly_infeasible' => $partlyInfeasible,
            'provisional' => $provisional,
            'binding' => $this->quota['binding'],
            'too_complex' => $tooComplex,
            'seated' => $seated,
            'contested' => $contested,
            'positions' => $positions,
            'scenarios' => null,
        ];
    }
}
