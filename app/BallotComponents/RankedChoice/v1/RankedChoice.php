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
        return self::runIteration($votes, $component);
    }

    /**
     * Calculates one round of Ranked Choice elimination, and decides whether to do another,
     * or return the list of all rounds.
     *
     * @param array $votes - The list of all votes cast for this component on a Ballot
     * @param BallotComponent $component
     * @param array $rounds - The elimination rounds, each containing one fewer options than the last
     * @param array $omit - The options that have been eliminated in previous rounds
     * @return void
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

            $runningTotal[$first] = $runningTotal[$first] + 1;
            return $runningTotal;
        }, $state);

        // Continue recursion if there are still more than 2 options
        if (count($state) > 2) {
            // The current least voted for option is added to the omit list.
            // If there is a tie for last place, omit only the first match.
            $omitees = array_keys($state, min($state));
            if (count($omitees) > 1) {
                $splits = [
                    '_state' => $state,
                    'splitElimination' => []
                ];
                foreach ($omitees as $omitee) {
                    $splitOmit = [...$omit, $omitee];
                    $splits['splitElimination'][$omitee] = [
                        'result' => self::runIteration($votes, $component, [], $splitOmit)
                    ];
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
     * @return void
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
        $state['eliminated'] = $omitee;
        $state['eliminated_previously'] = $omit;
        return $state;
    }

    public static function valuesToCsv($values, $component_id)
    {
        return implode(', ', $values[$component_id]);
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
