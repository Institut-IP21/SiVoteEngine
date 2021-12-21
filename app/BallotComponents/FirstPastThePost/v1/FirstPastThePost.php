<?php

namespace App\BallotComponents\FirstPastThePost\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Validation\Rule;

class FirstPastThePost extends BallotComponentType
{
    public static $needsOptions = true;

    public static $optionsValidator = [
        'options' => 'bail|required|array|min:2',
        'options.*' => 'bail|required|string|distinct|min:1'
    ];

    public static function strings()
    {
        return [
            'name' => __('components.fptp.name'),
            'description' => __('components.fptp.description'),
        ];
    }

    public static function calculateResults(array $votes, BallotComponent $component)
    {
        $state = collect($votes)
            ->groupBy(function ($vote) use ($component) {
                return $vote->values[$component->id];
            })
            ->map(function ($votes) {
                return $votes->count();
            })
            ->toArray();

        return self::annotateStateForVictory($state);
    }

    public static function annotateStateForVictory($state)
    {
        if (count($state) === 0) {
            return [
                'state' => $state,
                'total_votes' => 0,
                'winner' => null,
                'winners' => null
            ];
        }
        $winners = array_keys($state, max($state));
        if (count($winners) > 1) {
            $winner = 'tie';
        } else {
            $winner = $winners[0];
        }
        return [
            'state' => $state,
            'total_votes' => array_sum($state),
            'winner' => $winner,
            'winners' => $winners
        ];
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
