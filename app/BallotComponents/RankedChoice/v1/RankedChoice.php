<?php

declare(strict_types=1);

namespace App\BallotComponents\RankedChoice\v1;

use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\RankedChoiceResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Ranked Choice Voting (Instant Runoff) ballot component.
 *
 * Voters rank options in order of preference. If no option has a majority,
 * the option with the fewest votes is eliminated and those votes are
 * redistributed to the next preference until a winner is determined.
 */
final class RankedChoice extends AbstractBallotComponent
{
    #[\Override]
    protected function needsOptions(): bool
    {
        return true;
    }

    #[\Override]
    protected function usesLivewireForm(): bool
    {
        return true;
    }

    #[\Override]
    protected function getStrings(): array
    {
        return [
            'name' => __('components.rankedchoice.name'),
            'description' => __('components.rankedchoice.description'),
        ];
    }

    #[\Override]
    protected function getOptionsValidatorRules(): array
    {
        return [
            'options' => 'bail|required|array|min:2',
            'options.*' => 'bail|required|string|distinct|min:1',
        ];
    }

    #[\Override]
    public function calculateResults(Collection $votes, BallotComponent $component, bool $abstainable = false): ComponentResult
    {
        if ($votes->isEmpty()) {
            return RankedChoiceResult::empty();
        }

        $rounds = $this->runIteration($votes, $component);

        return RankedChoiceResult::fromRounds($rounds);
    }

    /**
     * Run the full LINEAR Ranked Choice elimination (D6/D7/D8). Each round tallies
     * first preferences for surviving options against CONTINUING ballots, tracks
     * EXHAUSTED ballots, and either declares a winner, eliminates a last-place
     * option (deterministic prior-round look-back, D6), or reports a non-conclusive
     * tie. No branching / splitElimination — the rounds list is flat.
     *
     * @param Collection<int, \App\Models\Vote> $votes
     * @param array<int, array<string, mixed>> $rounds Accumulated rounds (recursion carry)
     * @param list<string> $omit Options eliminated in previous rounds
     * @return array<int, array<string, mixed>>
     */
    private function runIteration(Collection $votes, BallotComponent $component, array $rounds = [], array $omit = []): array
    {
        // Surviving options, preserving roster order from $component->options (D10).
        $options = array_values(array_filter(
            $component->options ?? [],
            fn ($option): bool => !in_array($option, $omit, true)
        ));

        $tally = $this->tallyRound($votes, $component, $omit, $options);
        /** @var array<string, int> $state */
        $state = $tally['state'];
        $continuing = $tally['continuing'];
        $exhausted = $tally['exhausted'];

        if (count($state) === 0) {
            return $rounds;
        }

        // Integer strict majority over CONTINUING ballots (D7).
        $needed = intdiv($continuing, 2) + 1;
        $hasMajority = $continuing > 0 && max($state) >= $needed;

        if ($hasMajority || count($state) <= 2) {
            $top = max($state);
            $round = $this->decorateRound($state, $continuing, $exhausted);
            if ($top === 0) {
                $round['winner'] = null;
                return [...$rounds, $round];
            }
            $winners = array_keys($state, $top, true);
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
        $omitees = array_map('strval', array_keys($state, $min, true));

        if ($min === 0) {
            // Zero-vote batch elimination (D6.1): drop every zero-vote option.
            if (count($omitees) === count($state)) {
                $round = $this->decorateRound($state, $continuing, $exhausted);
                $round['winner'] = null;
                return [...$rounds, $round];
            }
            $eliminate = $omitees;
        } elseif (count($omitees) === 1) {
            $eliminate = $omitees[0];
        } else {
            // Non-zero last-place tie (D6.2/D6.3): deterministic look-back.
            $eliminate = $this->breakTieByLookback($omitees, $rounds);
            if ($eliminate === null) {
                $round = $this->decorateRound($state, $continuing, $exhausted);
                $round['winner'] = null;
                $round['tied'] = $omitees;
                return [...$rounds, $round];
            }
        }

        $round = $this->decorateRound($state, $continuing, $exhausted);
        $round = $this->annotateStateForOmission($round, $omit, $eliminate);
        $nextOmit = is_array($eliminate) ? [...$omit, ...$eliminate] : [...$omit, $eliminate];
        $rounds = [...$rounds, $round];

        return $this->runIteration($votes, $component, $rounds, $nextOmit);
    }

    /**
     * Tally first preferences for surviving options, reconciling each ballot
     * against $component->options (D9: out-of-options ranks are skipped). Returns
     * per-option state plus CONTINUING and EXHAUSTED ballot counts (D7/D8).
     *
     * @param Collection<int, \App\Models\Vote> $votes
     * @param list<string> $omit
     * @param list<string> $options surviving options, roster order
     * @return array{state: array<string, int>, continuing: int, exhausted: int}
     */
    private function tallyRound(Collection $votes, BallotComponent $component, array $omit, array $options): array
    {
        /** @var array<string, int> $state */
        $state = [];
        foreach ($options as $option) {
            $state[$option] = 0;
        }

        $continuing = 0;
        $exhausted = 0;
        /** @var list<string> $valid */
        $valid = array_values(array_map('strval', $component->options ?? []));

        foreach ($votes as $vote) {
            $values = $vote->values ?? null;

            if (empty($values) || !is_array($values) || !array_key_exists($component->id, $values)) {
                continue;
            }

            $ranking = $values[$component->id];
            if (!is_array($ranking) || count($ranking) === 0) {
                continue;
            }

            $first = null;
            foreach ($ranking as $pref) {
                if (!is_scalar($pref)) {
                    continue;
                }
                $label = (string) $pref;
                if (!in_array($label, $valid, true) || in_array($label, $omit, true)) {
                    continue;
                }
                $first = $label;
                break;
            }

            if ($first === null) {
                $rankedAnyValid = false;
                foreach ($ranking as $pref) {
                    if (is_scalar($pref) && in_array((string) $pref, $valid, true)) {
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
     * Among the tied last-place options, find the most recent prior round whose
     * tallies differ for them; the minimum there is the elimination candidate. If
     * several share that minimum, narrow and look at still-earlier rounds. Returns
     * null only when symmetric through ALL earlier rounds (genuinely tied, D6.3).
     *
     * @param list<string> $omitees
     * @param array<int, array<string, mixed>> $rounds prior rounds, oldest first
     * @param int|null $before exclusive upper bound on the round index to inspect
     */
    private function breakTieByLookback(array $omitees, array $rounds, ?int $before = null): ?string
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
                continue;
            }
            $priorMin = min($vals);
            /** @var list<string> $lowest */
            $lowest = array_map('strval', array_keys($vals, $priorMin, true));
            if (count($lowest) === 1) {
                return $lowest[0];
            }
            return $this->breakTieByLookback($lowest, $rounds, $r);
        }
        return null;
    }

    /**
     * Attach the per-round audit figures (D7/D8) to a round's tally.
     *
     * @param array<string, int> $state
     * @return array<string, mixed>
     */
    private function decorateRound(array $state, int $continuing, int $exhausted): array
    {
        $decorated = $state;
        $decorated['continuing'] = $continuing;
        $decorated['exhausted'] = $exhausted;
        $decorated['exhausted_running'] = $exhausted;
        return $decorated;
    }

    /**
     * Mark eliminated option(s) in a round state.
     *
     * @param array<string, mixed> $state
     * @param list<string> $omit
     * @param string|list<string> $omitee
     * @return array<string, mixed>
     */
    private function annotateStateForOmission(array $state, array $omit, string|array $omitee): array
    {
        $state['eliminated'] = is_array($omitee) ? implode(', ', $omitee) : $omitee;
        $state['eliminated_previously'] = $omit;
        return $state;
    }

    /**
     * Get frequency matrix of options at each ranking position.
     *
     * @param Collection<int, \App\Models\Vote> $votes
     * @return array<string, array<int, int>>
     */
    public function getTotals(Collection $votes, BallotComponent $component): array
    {
        $totals = [];
        $options = $component->options ?? [];

        foreach ($votes as $vote) {
            if (empty($vote->values)) {
                continue;
            }

            $values = $vote->values[$component->id] ?? [];
            if (!is_array($values)) {
                $values = [];
            }

            foreach ($options as $option) {
                if (!isset($totals[$option])) {
                    $totals[$option] = array_fill(0, count($options), 0);
                }

                $pos = array_search($option, $values, true);
                if ($pos !== false) {
                    $totals[$option][$pos] = ($totals[$option][$pos] ?? 0) + 1;
                }
            }
        }

        return $totals;
    }

    #[\Override]
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules
    {
        // The submission must be an array (a ranking). Without the explicit
        // `array` rule a crafted scalar value bypasses the `.*` option
        // whitelist entirely and is stored verbatim. `distinct` rejects a
        // ranking that lists the same option more than once.
        return new ValidationRules([
            $component->id => [$election->abstainable ? 'nullable' : 'required', 'array'],
            "{$component->id}.*" => ['distinct', Rule::in($component->options)],
        ]);
    }

    #[\Override]
    public function valuesToCsv(array $values, string $componentId): string
    {
        if (!array_key_exists($componentId, $values)) {
            return '';
        }

        $value = $values[$componentId];
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

}
