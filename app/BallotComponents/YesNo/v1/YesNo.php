<?php

namespace App\BallotComponents\YesNo\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;

class YesNo extends BallotComponentType
{
    /** @var bool */
    public static $needsOptions = false;

    /** @var list<string> */
    public static $presetOptions = ['yes', 'no'];

    public static $optionsValidator = [
        'options' => 'in:yes,no'
    ];

    /** @return array<string, mixed> */
    public static function strings(): array
    {
        return [
            'name' => __('components.yesno.name'),
            'description' => __('components.yesno.description'),
        ];
    }

    /**
     * @param array<int, Vote> $votes
     * @return array<string, mixed>
     */
    public static function calculateResults(array $votes, BallotComponent $component): array
    {
        /** @var array<string, int> $result */
        $result = array_reduce($votes, function (array $runningTotal, Vote $vote) use ($component): array {
            if (empty($vote->values)) {
                return $runningTotal;
            }
            $value = $vote->values[$component->id];
            $runningTotal[$value] = array_key_exists($value, $runningTotal) ? $runningTotal[$value] + 1 : 1;
            return $runningTotal;
        }, []);

        return self::annotateStateForVictory($result);
    }

    /**
     * @param array<string, int> $state
     * @return array<string, mixed>
     */
    public static function annotateStateForVictory(array $state): array
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

    /** @return array<string, mixed> */
    public static function getSubmissionValidator(BallotComponent $component, Election $election): array
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

    /**
     * @param mixed $options
     */
    public static function validateOptions($options): bool
    {
        $validator = Validator::make(['options' => $options], static::$optionsValidator);
        $messages = $validator->errors();

        return $messages->isEmpty();
    }
}
