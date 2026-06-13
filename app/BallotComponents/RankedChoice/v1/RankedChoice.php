<?php

namespace App\BallotComponents\RankedChoice\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;

class RankedChoice extends BallotComponentType
{
    /** @var bool */
    public static $needsOptions = true;

    /** @var bool */
    public static $livewireForm = true;

    public static $optionsValidator = [
        'options' => 'bail|required|array|min:2',
        'options.*' => 'bail|required|string|distinct|min:1'
    ];

    /** @return array<string, mixed> */
    public static function strings(): array
    {
        return [
            'name' => __('components.rankedchoice.name'),
            'description' => __('components.rankedchoice.description'),
        ];
    }


    /**
     * @param array<int, Vote> $votes
     * @param BallotComponent $component
     * @return array<string, mixed>
     */
    public static function calculateResults(array $votes, BallotComponent $component, bool $abstainable = false): array
    {
        if (count($votes) === 0) {
            return [
                'rounds' => [],
                'result' => [
                    'winners' => [],
                    'conclussive' => null,
                    'conclussive_winner' => null
                ]
            ];
        }
        $rounds = self::runIteration($votes, $component);
        return [
            'rounds' => $rounds,
            'result' => self::annotateFinalState($rounds)
        ];
    }

    /**
     * Runs the full (now LINEAR) Ranked Choice elimination. Each round tallies first
     * preferences for surviving options against CONTINUING ballots (D7), tracks the
     * count of exhausted ballots (D8), and either declares a winner, eliminates a
     * last-place option (deterministic prior-round look-back per D6), or reports a
     * non-conclusive tie. No branching / splitElimination — the rounds list is flat.
     *
     * @param array<int, Vote> $votes - The list of all votes cast for this component on a Ballot
     * @param BallotComponent $component
     * @param array<int, array<string, mixed>> $rounds - Accumulated rounds (recursion carry)
     * @param list<string> $omit - The options eliminated in previous rounds
     * @return array<int, array<string, mixed>> - The list of all rounds
     */
    public static function runIteration(array $votes, BallotComponent $component, array $rounds = [], array $omit = []): array
    {
        // Surviving options, preserving the roster order from $component->options (D10).
        $options = array_values(array_filter(
            $component->options,
            fn ($option): bool => !in_array($option, $omit, true)
        ));

        $tally = self::tallyRound($votes, $component, $omit, $options);
        /** @var array<string, int> $state */
        $state = $tally['state'];
        $continuing = $tally['continuing'];
        $exhausted = $tally['exhausted'];

        // No surviving options at all (over-omitted / empty roster). No winner.
        if (count($state) === 0) {
            return $rounds;
        }

        // Integer strict majority over CONTINUING ballots (D7). All min/max/array_keys
        // below operate on the PURE tally ($state holds only option => int here); audit
        // keys are merged in by decorateRound at the very end so they never pollute it.
        $needed = intdiv($continuing, 2) + 1;
        $hasMajority = $continuing > 0 && max($state) >= $needed;

        // Win condition: an option holds a continuing-ballot majority, OR we are down
        // to the final two (plurality decides, the down-to-two termination).
        if ($hasMajority || count($state) <= 2) {
            $top = max($state);
            $round = self::decorateRound($state, $continuing, $exhausted);
            if ($top === 0) {
                // No valid votes for any surviving option (e.g. a sole option that
                // nobody ranked): there is no winner.
                $round['winner'] = null;
                return [...$rounds, $round];
            }
            $winners = array_keys($state, $top, true);
            // A tie for the top tally is recorded as winner=null + tied labels (D6);
            // the literal 'tie' sentinel is never stored.
            if (count($winners) > 1) {
                $round['winner'] = null;
                $round['tied'] = array_map('strval', $winners);
            } else {
                $round['winner'] = (string) $winners[0];
            }
            return [...$rounds, $round];
        }

        // Otherwise eliminate a last-place option and recurse.
        $min = min($state);
        /** @var list<string> $omitees */
        $omitees = array_keys($state, $min, true);

        if ($min === 0) {
            // Zero-vote batch elimination (D6.1): drop every zero-vote option together.
            // If EVERY surviving option is at zero (e.g. all ballots abstained or
            // exhausted), nothing is winnable — stop here, non-conclusive (no crash).
            if (count($omitees) === count($state)) {
                $round = self::decorateRound($state, $continuing, $exhausted);
                $round['winner'] = null;
                return [...$rounds, $round];
            }
            $eliminate = $omitees;
        } elseif (count($omitees) === 1) {
            $eliminate = $omitees[0];
        } else {
            // Non-zero last-place tie (D6.2/D6.3): deterministic prior-round look-back.
            $eliminate = self::breakTieByLookback($omitees, $rounds);
            if ($eliminate === null) {
                // Genuinely symmetric through every prior round (D6.3): cannot break
                // deterministically -> non-conclusive tie among the tied options.
                $round = self::decorateRound($state, $continuing, $exhausted);
                $round['winner'] = null;
                $round['tied'] = array_map('strval', $omitees);
                return [...$rounds, $round];
            }
        }

        $round = self::decorateRound($state, $continuing, $exhausted);
        $round = self::annotateStateForOmission($round, $omit, $eliminate);
        $nextOmit = is_array($eliminate) ? [...$omit, ...$eliminate] : [...$omit, $eliminate];
        $rounds = [...$rounds, $round];

        return self::runIteration($votes, $component, $rounds, $nextOmit);
    }

    /**
     * Tally first preferences for the surviving options over the full vote set,
     * reconciling each ballot against $component->options (D9: out-of-options ranks
     * are skipped, never transferred to). Returns the per-option state plus the
     * CONTINUING and EXHAUSTED ballot counts for this round (D7/D8).
     *
     * @param array<int, Vote> $votes
     * @param BallotComponent $component
     * @param list<string> $omit
     * @param list<string> $options - surviving options, roster order
     * @return array{state: array<string, int>, continuing: int, exhausted: int}
     */
    private static function tallyRound(array $votes, BallotComponent $component, array $omit, array $options): array
    {
        /** @var array<string, int> $state */
        $state = [];
        foreach ($options as $option) {
            $state[$option] = 0;
        }

        $continuing = 0;
        $exhausted = 0;
        /** @var list<string> $valid */
        $valid = array_values(array_map('strval', $component->options));

        foreach ($votes as $vote) {
            $values = $vote['values'] ?? null;

            // Unanswered ballot / no answer for this component: not a continuing ballot,
            // not an exhausted one either (it never entered the contest).
            if (empty($values) || !is_array($values) || !array_key_exists($component->id, $values)) {
                continue;
            }

            $ranking = $values[$component->id];
            if (!is_array($ranking) || count($ranking) === 0) {
                continue;
            }

            // Walk the ranking for the highest surviving, valid preference. Ranks not in
            // $component->options are invalid and skipped (D9); eliminated options are
            // skipped (transferred past). A ballot whose only surviving preferences are
            // exhausted counts as exhausted (D8).
            $first = null;
            foreach ($ranking as $pref) {
                if (!is_scalar($pref)) {
                    continue;
                }
                $label = (string) $pref;
                if (!in_array($label, $valid, true)) {
                    continue;
                }
                if (in_array($label, $omit, true)) {
                    continue;
                }
                $first = $label;
                break;
            }

            if ($first === null) {
                // The ballot ranked at least one valid option but all such options have
                // been eliminated: it is exhausted this round.
                $rankedAnyValid = false;
                foreach ($ranking as $pref) {
                    if (is_scalar($pref) && in_array($pref, $valid, true)) {
                        $rankedAnyValid = true;
                        break;
                    }
                }
                if ($rankedAnyValid) {
                    $exhausted++;
                }
                continue;
            }

            $state[$first] += 1;
            $continuing++;
        }

        return ['state' => $state, 'continuing' => $continuing, 'exhausted' => $exhausted];
    }

    /**
     * Deterministic prior-round look-back (D6.2 / D6.3), with backward RECURSION.
     *
     * Among the tied last-place options, find the most recent prior round whose tallies
     * differ for them: the option(s) holding the minimum tally there are the elimination
     * candidates. If exactly one, eliminate it. If several share that minimum, NARROW to
     * that subset and keep looking at EVEN-EARLIER rounds (rounds strictly before the
     * distinguishing one) to break the sub-tie. Only return null when the candidates are
     * tied through ALL still-earlier rounds (genuinely symmetric, D6.3) — i.e. no earlier
     * round ever separates them.
     *
     * Deterministic and reproducible: no RNG, pure function of the prior tallies.
     *
     * @param list<string> $omitees - the options currently tied for last
     * @param array<int, array<string, mixed>> $rounds - prior rounds, oldest first
     * @param int|null $before - exclusive upper bound on the round index to inspect
     *                           (look only at rounds with index < $before); null = all
     * @return string|null
     */
    private static function breakTieByLookback(array $omitees, array $rounds, ?int $before = null): ?string
    {
        $start = $before === null ? count($rounds) - 1 : $before - 1;
        for ($r = $start; $r >= 0; $r--) {
            $prior = $rounds[$r];
            /** @var array<string, int> $vals */
            $vals = [];
            foreach ($omitees as $option) {
                $vals[$option] = is_int($prior[$option] ?? null) ? $prior[$option] : 0;
            }
            if (count($vals) === 0 || count(array_unique($vals)) === 1) {
                // Identical for the tied options in this prior round: keep looking back.
                continue;
            }
            $priorMin = min($vals);
            /** @var list<string> $lowest */
            $lowest = array_map('strval', array_keys($vals, $priorMin, true));
            if (count($lowest) === 1) {
                return $lowest[0];
            }
            // This distinguishing round leaves a SUBSET tied for the lowest tally:
            // narrow to that subset and recurse on the rounds strictly before it.
            return self::breakTieByLookback($lowest, $rounds, $r);
        }
        // No (earlier) round ever separates the tied options: genuinely symmetric.
        return null;
    }

    /**
     * Attach the per-round audit figures (D7/D8) to a round's tally. Exhaustion is
     * recomputed from scratch each round over the full vote set, so the running total
     * equals this round's count.
     *
     * @param array<string, int> $state
     * @return array<string, mixed>
     */
    private static function decorateRound(array $state, int $continuing, int $exhausted): array
    {
        $decorated = $state;
        $decorated['continuing'] = $continuing;
        $decorated['exhausted'] = $exhausted;
        // Cumulative-by-construction: under the current full-recompute model, exhaustion
        // is re-tallied over the ENTIRE vote set every round, so the running total is
        // identical to this round's per-round count. If a future maintainer switches to
        // incremental tallying (accumulating only the delta each round), this must be
        // changed to carry the running sum across rounds instead of mirroring $exhausted.
        $decorated['exhausted_running'] = $exhausted;
        return $decorated;
    }

    /**
     * Returns a matrix containing the frequencies of each option being in each possible place in the ranking
     *
     * @param array<int, Vote> $votes
     * @param BallotComponent $component
     * @return array<string, list<int>> - The frequencies of each option being in each place in the ranking
     */
    public static function getTotals(array $votes, BallotComponent $component): array
    {
        return array_reduce($votes, function ($runningTotal, $vote) use ($component) {
            if (empty($vote['values']) || !array_key_exists($component->id, $vote['values'])) {
                return $runningTotal;
            }
            $values = $vote['values'][$component->id];
            foreach ($component->options as $i => $option) {
                $pos = array_search($option, $values);
                if ($pos !== false) {
                    if (!array_key_exists($option, $runningTotal)) {
                        $runningTotal[$option] = array_fill(0, count($component->options), 0);
                    }
                    // Record this placement. Previously the first ballot to
                    // mention each option only zero-filled and skipped the
                    // increment, undercounting every option by one.
                    $runningTotal[$option][$pos] = ($runningTotal[$option][$pos] ?? 0) + 1;
                } else {
                    if (!array_key_exists($option, $runningTotal)) {
                        $runningTotal[$option] = array_fill(0, count($component->options), 0);
                    }
                }
            }
            return $runningTotal;
        }, []);
    }

    /**
     * @param array<string, mixed> $state
     * @param list<string> $omit
     * @param string|list<string> $omitee
     * @return array<string, mixed>
     */
    public static function annotateStateForOmission(array $state, array $omit, string|array $omitee): array
    {
        $state['eliminated'] = is_array($omitee) ? implode(', ', $omitee) : $omitee;
        $state['eliminated_previously'] = $omit;
        return $state;
    }

    /**
     * Derive the final result from the LINEAR rounds list (D6). The terminal round
     * carries either a single `winner` label (conclusive) or `winner === null` plus a
     * `tied` list of the genuinely-tied option labels (non-conclusive). The literal
     * 'tie' sentinel never appears, so it can never leak into `winners` (#14 guard).
     *
     * @param array<int, array<string, mixed>> $rounds
     * @return array{winners: array<string>, conclussive: bool, conclussive_winner: string|null}
     */
    public static function annotateFinalState(array $rounds): array
    {
        $final = end($rounds);

        // No terminal round at all (every option eliminated with no decision) — treat
        // as non-conclusive with no winners.
        if (!is_array($final)) {
            return [
                'winners' => [],
                'conclussive' => false,
                'conclussive_winner' => null,
            ];
        }

        $winner = $final['winner'] ?? null;

        if (is_string($winner)) {
            // A single conclusive winner.
            return [
                'winners' => [$winner],
                'conclussive' => true,
                'conclussive_winner' => $winner,
            ];
        }

        // Non-conclusive: surface the tied option labels (if any were recorded).
        /** @var list<string> $tied */
        $tied = [];
        if (isset($final['tied']) && is_array($final['tied'])) {
            $tied = array_values(array_map('strval', $final['tied']));
        }

        return [
            'winners' => $tied,
            'conclussive' => false,
            'conclussive_winner' => null,
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param string $component_id
     */
    public static function valuesToCsv(array $values, string $component_id): string
    {
        if (array_key_exists($component_id, $values)) {
            return implode(', ', (array) $values[$component_id]);
        }
        return '';
    }

    /** @return array<string, mixed> */
    public static function getSubmissionValidator(BallotComponent $component, Election $election): array
    {
        $id = $component->id;
        $options = $component->options;
        return [
            $id => [
                $election->abstainable ? 'nullable' : 'required',
            ],
            "$id.*" => [
                Rule::in($options)
            ]
        ];
    }

    /**
     * @param mixed $options
     */
    public static function validateOptions($options): bool
    {
        //TODO since this is just for CLI, it could be removed and implemented there I think...
        $validator = Validator::make(['options' => $options], static::$optionsValidator);
        $messages = $validator->errors();

        return $messages->isEmpty();
    }
}
