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
    public static function calculateResults(array $votes, BallotComponent $component): array
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
     * Calculates one round of Ranked Choice elimination, and decides whether to do another,
     * or return the list of all rounds.
     *
     * @param array<int, Vote> $votes - The list of all votes cast for this component on a Ballot
     * @param BallotComponent $component
     * @param array<int, array<string, mixed>> $rounds - The elimination rounds, each containing one fewer options than the last
     * @param list<string> $omit - The options that have been eliminated in previous rounds
     * @return array<int, array<string, mixed>> - The list of all rounds
     */
    public static function runIteration(array $votes, BallotComponent $component, array $rounds = [], array $omit = []): array
    {
        // Only loop over non-omited options
        $options = array_diff($component->options, $omit);
        // Preset all the options to value 0
        $state = array_reduce($options, function ($acc, $option) {
            $acc[$option] = 0;
            return $acc;
        }, []);

        $number_of_votes_cast = collect($votes)->filter(function ($vote) {
            return !empty($vote['values']);
        })->count();

        $state = array_reduce($votes, function ($runningTotal, $vote) use ($component, $omit) {
            // Skip the votes that were never cast
            if (empty($vote['values'])) {
                return $runningTotal;
            }

            if (!array_key_exists($component->id, $vote['values'])) {
                return $runningTotal;
            }

            $values = $vote['values'][$component->id];

            if (!count($values)) {
                return $runningTotal;
            }

            // Remove all omited options from the beginning of the array
            while (count($values) && in_array($values[0], $omit)) {
                array_shift($values);
            }

            // The next first value is what we want
            $first = array_shift($values);

            if (!$first) {
                return $runningTotal;
            }

            $runningTotal[$first] += 1;
            return $runningTotal;
        }, $state);

        $current_winner_has_majority = max($state) >= $number_of_votes_cast / 2 + 1;

        // Continue running elimination rounds if there are still more than 2 options, and none of the options have a majority.
        if (count($state) > 2 && !$current_winner_has_majority) {
            // The current least voted for option is added to the omit list.
            // If there is a tie for last place, we run through both scenarios of eliminating those.
            /** @var list<string> $omitees */
            $omitees = array_keys($state, min($state));
            if (count($omitees) > 1) {
                // The special case is for options that got 0 votes, where we choose to eliminate them all.
                if (min($state) === 0) {
                    $state = self::annotateStateForOmission($state, $omit, $omitees);
                    $nextOmit = [...$omit, ...$omitees];
                    $rounds = [...$rounds, $state];
                    return self::runIteration($votes, $component, $rounds, $nextOmit);
                }
                $splits = [
                    '_state' => $state,
                    'splitElimination' => []
                ];
                foreach ($omitees as $omitee) {
                    $splitOmit = [...$omit, $omitee];
                    $splits['splitElimination'][$omitee] = self::runIteration($votes, $component, [], $splitOmit);
                }
                return [...$rounds, $splits];
            }
            /** @var string $omitee */
            $omitee = array_pop($omitees);
            $state = self::annotateStateForOmission($state, $omit, $omitee);
            $nextOmit = [...$omit, $omitee];
            $rounds = [...$rounds, $state];
            return self::runIteration($votes, $component, $rounds, $nextOmit);
        }

        $state = self::annotateStateForVictory($state);
        $rounds = [...$rounds, $state];

        return $rounds;
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
            if (empty($vote['values'])) {
                return $runningTotal;
            }
            $values = $vote['values'][$component->id];
            foreach ($component->options as $i => $option) {
                $pos = array_search($option, $values);
                if ($pos !== false) {
                    if (array_key_exists($option, $runningTotal)) {
                        $runningTotal[$option][$pos] = ($runningTotal[$option][$pos] ?? 0) + 1;
                    } else {
                        $runningTotal[$option] = array_fill(0, count($component->options), 0);
                    }
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
     * @param non-empty-array<string, mixed> $state
     * @return array<string, mixed>
     */
    public static function annotateStateForVictory(array $state): array
    {
        $winners = array_keys($state, max($state));
        if (count($winners) > 1) {
            $state['winner'] = 'tie';
        } else {
            $state['winner'] = array_pop($winners);
        }
        return $state;
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
     * @param array<int, array<string, mixed>> $rounds
     * @return array{winners: array<string>, conclussive: bool, conclussive_winner: string|null}
     */
    public static function annotateFinalState(array $rounds): array
    {
        $winners = [];
        array_walk_recursive($rounds, function ($i, $el) use (&$winners) {
            if ($el === 'winner') {
                $winners[] = $i;
            }
        });
        $unique_winners = array_unique($winners);
        return [
            'winners' => $unique_winners,
            'conclussive' => count($unique_winners) === 1,
            'conclussive_winner' => array_pop($unique_winners)
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @param string $component_id
     */
    public static function valuesToCsv(array $values, string $component_id): string
    {
        if (array_key_exists($component_id, $values)) {
            return implode(', ', $values[$component_id]);
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
