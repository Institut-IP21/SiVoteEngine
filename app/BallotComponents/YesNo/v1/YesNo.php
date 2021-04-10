<?php

namespace App\BallotComponents\YesNo\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Validation\Rule;

class YesNo extends BallotComponentType
{
    public static $needsOptions = false;
    public static $presetOptions = ['yes', 'no'];

    public static $optionsValidator = [
        'options' => 'in:yes,no'
    ];

    public static function calculateResults(array $votes, BallotComponent $component)
    {
        return array_reduce($votes, function ($runningTotal, $vote) use ($component) {
            if (empty($vote['values'])) {
                return $runningTotal;
            }
            $value = $vote['values'][$component->id];
            $runningTotal[$value] = array_key_exists($value, $runningTotal) ? $runningTotal[$value] + 1 : 1;
            return $runningTotal;
        }, []);
    }

    public static function getSubmissionValidator(BallotComponent $component, Election $election)
    {
        $id = $component->id;
        $options = static::$presetOptions;
        if ($election->abstainable) {
            $options[] = 'abstain';
        }
        return [
            $id => [
                'required',
                Rule::in($options)
            ]
        ];
    }

    public static function validateOptions($options)
    {
        $validator = Validator::make(['options' => $options], static::$optionsValidator);
        $messages = $validator->errors();

        return $messages->isEmpty();
    }
}
