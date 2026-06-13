<?php

namespace App\BallotComponents\ApprovalVote\v1;

use Illuminate\Support\Facades\Validator;
use App\BallotComponents\BallotComponentType;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;

class ApprovalVote extends BallotComponentType
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
            'name' => __('components.approval.name'),
            'description' => __('components.approval.description'),
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function valuesToCsv(array $values, string $component_id): mixed
    {
        if (array_key_exists($component_id, $values)) {
            // calculateResults tolerates a scalar answer via (array); mirror that
            // here so a single-option string does not raise a TypeError in implode.
            return implode(', ', (array) $values[$component_id]);
        }
        return '';
    }

    /**
     * Approval voting (D1/D2/D9/D10).
     *
     * Per ballot:
     *  - component key absent OR null value: an abstention when abstainable
     *    (a non-participant), otherwise an invalid/blank ballot (also a
     *    non-participant — it cast no real approval). Neither counts in `voters`.
     *  - otherwise the ballot participates (`voters`++) and each approved label is
     *    reconciled against $component->options: a known option increments `state`,
     *    anything else (unknown label or non-scalar) is counted in `invalid` and is
     *    never winnable. An empty approval set is a participant who approved nobody.
     *
     * @param array<int, Vote> $votes
     * @return array<string, mixed>
     */
    public static function calculateResults(array $votes, BallotComponent $component, bool $abstainable = false): array
    {
        /** @var array<int, string> $options */
        $options = $component->options;

        // D10: full roster — seed every option at 0, in options order.
        $state = [];
        foreach ($options as $option) {
            $state[(string) $option] = 0;
        }
        $allowed = array_flip(array_map('strval', $options));

        $voters = 0;
        $abstentions = 0;
        $invalid = 0;

        foreach ($votes as $vote) {
            $values = is_array($vote->values) ? $vote->values : [];
            $hasKey = array_key_exists($component->id, $values);
            $answer = $hasKey ? $values[$component->id] : null;

            // Absent key or null value: not a participant. A legitimate abstention
            // only when the election is abstainable (D9); otherwise it is an
            // invalid/blank ballot. Neither counts in `voters` nor can win.
            if (!$hasKey || $answer === null) {
                $abstainable ? $abstentions++ : $invalid++;
                continue;
            }

            // A participating ballot: scalar answer wraps to a one-element array.
            $voters++;
            $approvals = is_array($answer) ? $answer : [$answer];

            foreach ($approvals as $label) {
                // D9: only scalar labels present in options are real approvals;
                // a non-scalar or an unknown label is invalid (never winnable).
                if (is_scalar($label) && isset($allowed[(string) $label])) {
                    $state[(string) $label]++;
                } else {
                    $invalid++;
                }
            }
        }

        $totalApprovals = array_sum($state);

        // Winner: most approvals among real options; abstain/invalid excluded by
        // construction (they are never in $state). No valid approvals → no winner.
        if ($totalApprovals === 0 || $state === []) {
            $winner = null;
            $winners = [];
        } else {
            $winners = array_keys($state, max($state));
            $winner = count($winners) > 1 ? 'tie' : $winners[0];
        }

        return [
            'state' => $state,
            'voters' => $voters,
            'total_approvals' => $totalApprovals,
            'abstentions' => $abstentions,
            'invalid' => $invalid,
            'total_ballots' => $voters + $abstentions,
            'winner' => $winner,
            'winners' => $winners,
        ];
    }

    /** @return array<string, mixed> */
    public static function getSubmissionValidator(BallotComponent $component, Election $election): array
    {
        $id = $component->id;
        $options = $component->options;
        return [
            $id => [
                $election->abstainable ? 'nullable' : 'required',
                // The answer must be an array so the element rule below actually
                // binds — without this a scalar submission bypasses Rule::in.
                'array',
            ],
            "$id.*" => [
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
