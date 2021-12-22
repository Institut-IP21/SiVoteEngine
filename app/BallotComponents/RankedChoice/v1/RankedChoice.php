<?php

namespace App\BallotComponents\RankedChoice\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Validation\Rule;

class RankedChoice extends BallotComponentType
{
    public static $needsOptions = true;
    public static $livewireForm = true;

    public static $optionsValidator = [
        'options' => 'bail|required|array|min:2',
        'options.*' => 'bail|required|string|distinct|min:1'
    ];

    public static function strings()
    {
        return [
            'name' => __('components.rankedchoice.name'),
            'description' => __('components.rankedchoice.description'),
        ];
    }


    public static function calculateResults($votes, $component)
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
     * @param array $votes - The list of all votes cast for this component on a Ballot
     * @param BallotComponent $component
     * @param array $rounds - The elimination rounds, each containing one fewer options than the last
     * @param array $omit - The options that have been eliminated in previous rounds
     * @return array - The list of all rounds
     */
    public static function runIteration($votes, $component, $rounds = [], $omit = [])
    {
        // Only loop over non-omited options
        $options = array_diff($component->options, $omit);
        // Preset all the options to value 0
        $state = array_reduce($options, function ($acc, $option) {
            $acc[$option] = 0;
            return $acc;
        }, []);

        $number_of_votes_cast = collect($votes)->countBy(function ($vote) {
            return !empty($vote['values']);
        })->first();

        $state = array_reduce($votes, function ($runningTotal, $vote) use ($component, $omit) {
            // Skip the votes that were never cast
            if (empty($vote['values'])) {
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
     * @param array $votes
     * @param BallotComponent $component
     * @return array - The frequencies of each option being in each place in the ranking
     */
    public static function getTotals($votes, $component)
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

    public static function annotateStateForVictory($state)
    {
        $winners = array_keys($state, max($state));
        if (count($winners) > 1) {
            $state['winner'] = 'tie';
        } else {
            $state['winner'] = array_pop($winners);
        }
        return $state;
    }

    public static function annotateStateForOmission($state, $omit, $omitee)
    {
        $state['eliminated'] = is_array($omitee) ? implode(', ', $omitee) : $omitee;
        $state['eliminated_previously'] = $omit;
        return $state;
    }

    public static function annotateFinalState($rounds)
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

    public static function valuesToCsv($values, $component_id)
    {
        if (array_key_exists($component_id, $values)) {
            return implode(', ', $values[$component_id]);
        }
        return '';
    }

    public static function getSubmissionValidator(BallotComponent $component, Election $election)
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

    public static function validateOptions($options)
    {
        //TODO since this is just for CLI, it could be removed and implemented there I think...
        $validator = Validator::make(['options' => $options], static::$optionsValidator);
        $messages = $validator->errors();

        return $messages->isEmpty();
    }
}
