<?php

namespace App\BallotComponents\FirstPassThePost\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Validation\Rule;
use phpDocumentor\Reflection\Types\Self_;

class FirstPassThePost extends BallotComponentType
{
    public static $needsOptions = true;

    public static $optionsValidator = [
        'options' => 'bail|required|array|min:2',
        'options.*' => 'bail|required|string|distinct|min:1'
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
        $options = $component->options;
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
        //TODO since this is just for CLI, it could be removed and implemented there I think...
        $validator = Validator::make(['options' => $options], static::$optionsValidator);
        $messages = $validator->errors();

        return $messages->isEmpty();
    }
}
