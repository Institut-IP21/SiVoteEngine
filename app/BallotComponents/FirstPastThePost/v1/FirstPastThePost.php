<?php

namespace App\BallotComponents\FirstPastThePost\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;

class FirstPastThePost extends BallotComponentType
{
    /** @var bool */
    public static $needsOptions = true;

    public static $optionsValidator = [
        'options' => 'bail|required|array|min:2',
        'options.*' => 'bail|required|string|distinct|min:1'
    ];

    /** @return array<string, mixed> */
    public static function strings(): array
    {
        return [
            'name' => __('components.fptp.name'),
            'description' => __('components.fptp.description'),
        ];
    }

    /**
     * @param array<int, Vote> $votes
     * @return array<string, mixed>
     */
    public static function calculateResults(array $votes, BallotComponent $component): array
    {
        $state = collect($votes)
            ->groupBy(function (Vote $vote) use ($component): string {
                return ($vote->values ?? [])[$component->id] ?? 'abstain';
            })
            ->map(function (mixed $votes): int {
                return $votes->count();
            })
            ->toArray();

        return self::annotateStateForVictory($state);
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
