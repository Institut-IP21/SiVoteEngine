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

    public static function calculateResults($votes, $component)
    {
        return self::runIteration($votes, $component);
    }

    /**
     * Calculates one round of Ranked Choice elimination, and decides whether to do another,
     * or return the list of all rounds.
     *
     * @param array $votes
     * @param BallotComponent $component
     * @param array $rounds
     * @param array $omit
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
            // The current least voted for option is added to omit list.
            // If there is a tie for last place, omit only the first match.
            $omitThisRound = array_keys($state, min($state))[0];
            array_push($omit, $omitThisRound);
            $state = self::annotateStateForOmission($state, $omitThisRound);
            array_push($rounds, $state);
            return self::runIteration($votes, $component, $rounds, $omit);
        }

        $state = self::annotateStateForVictory($state);
        array_push($rounds, $state);

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
        $state['winner'] = array_keys($state, max($state))[0];
        return $state;
    }
    public static function annotateStateForOmission($state, $omit)
    {
        $state['eliminated'] = $omit;
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
