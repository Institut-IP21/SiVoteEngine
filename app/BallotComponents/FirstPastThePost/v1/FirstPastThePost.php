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

    /** The literal token a voter's stored answer carries for a deliberate abstention. */
    private const ABSTAIN = 'abstain';

    /**
     * @param array<int, Vote> $votes
     * @return array<string, mixed>
     */
    public static function calculateResults(array $votes, BallotComponent $component, bool $abstainable = false): array
    {
        // D10: seed the full roster — every declared option in options order at 0.
        /** @var array<string, int> $state */
        $state = [];
        foreach ($component->options as $option) {
            if (is_scalar($option)) {
                $state[(string) $option] = 0;
            }
        }

        $abstentions = 0;
        $invalid = 0;

        foreach ($votes as $vote) {
            $values = $vote->values;
            $answer = is_array($values) && array_key_exists($component->id, $values)
                ? $values[$component->id]
                : null;

            // Blank / missing / null: abstain when the election is abstainable, else invalid (D9).
            if ($answer === null || $answer === '') {
                if ($answer === null) {
                    $abstainable ? $abstentions++ : $invalid++;
                    continue;
                }
                // empty string is never a legitimate abstention token — invalid (D9).
                $invalid++;
                continue;
            }

            // A non-scalar (e.g. array) where a scalar is expected → invalid, no TypeError (D9).
            if (! is_scalar($answer)) {
                $invalid++;
                continue;
            }

            $answer = (string) $answer;

            // The abstain token: a deliberate abstention only on an abstainable election (D9).
            if ($answer === self::ABSTAIN) {
                $abstainable ? $abstentions++ : $invalid++;
                continue;
            }

            // Reconcile against the declared options; anything else is invalid and never winnable (D9).
            if (array_key_exists($answer, $state)) {
                $state[$answer]++;
            } else {
                $invalid++;
            }
        }

        return self::annotateStateForVictory($state, $abstentions, $invalid);
    }

    /**
     * @param array<string, int> $state full roster (options order), real options only
     * @return array<string, mixed>
     */
    public static function annotateStateForVictory(array $state, int $abstentions = 0, int $invalid = 0): array
    {
        // valid_votes is the percentage denominator (D1): real option votes only,
        // excluding abstentions and invalid values.
        $validVotes = array_sum($state);
        $totalVotes = $validVotes + $abstentions + $invalid;

        $base = [
            'state' => $state,
            'valid_votes' => $validVotes,
            'abstentions' => $abstentions,
            'invalid' => $invalid,
            'total_votes' => $totalVotes,
        ];

        // No valid votes → no winner (abstain/invalid can never win). An all-zero
        // (or empty) roster has nothing to win, so guard before max().
        if ($validVotes === 0 || count($state) === 0) {
            return $base + [
                'winner' => null,
                'winners' => [],
            ];
        }

        $winners = array_keys($state, max($state));
        $winner = count($winners) > 1 ? 'tie' : $winners[0];

        return $base + [
            'winner' => $winner,
            'winners' => $winners,
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
