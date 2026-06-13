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
            if (empty($vote->values) || !array_key_exists($component->id, $vote->values)) {
                // Skip ballots that did not answer this component. Reading an
                // absent key produced a null value that PHP cast to the empty
                // string '', creating a phantom '' bucket that counted and could
                // win.
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
        // Abstentions are tallied and displayed (and remain in total_votes and
        // therefore the percentage denominator), but must never win or tie the
        // outcome, so they are excluded from the winner computation.
        $candidates = $state;
        unset($candidates['abstain']);

        if (count($candidates) === 0) {
            // Only abstentions were cast — no option can win.
            return [
                'state' => $state,
                'total_votes' => array_sum($state),
                'winner' => null,
                'winners' => []
            ];
        }

        $winners = array_keys($candidates, max($candidates));
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
