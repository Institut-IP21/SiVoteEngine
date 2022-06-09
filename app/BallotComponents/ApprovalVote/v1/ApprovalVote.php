<?php

namespace App\BallotComponents\ApprovalVote\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Validation\Rule;

class ApprovalVote extends BallotComponentType
{
    public static $needsOptions = true;

    public static $optionsValidator = [
        'options' => 'bail|required|array|min:2',
        'options.*' => 'bail|required|string|distinct|min:1'
    ];

    public static function strings()
    {
        return [
            'name' => __('components.approval.name'),
            'description' => __('components.approval.description'),
        ];
    }

    public static function valuesToCsv($values, $component_id)
    {
        if (array_key_exists($component_id, $values)) {
            return implode(', ', $values[$component_id]);
        }
        return '';
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
