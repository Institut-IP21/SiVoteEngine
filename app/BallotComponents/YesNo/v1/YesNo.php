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
     * Tally a single binary motion.
     *
     * Contract (D1/D4/D5/D9/D10):
     *  - state          [yes => int, no => int]  full roster, both seeded 0
     *  - valid_votes    int                      yes + no (the % / pass denominator)
     *  - abstentions    int                      legitimate abstain tokens (abstainable only)
     *  - invalid        int                      anything not yes/no/(abstain when abstainable)
     *  - total_votes    int                      valid_votes + abstentions + invalid
     *  - winner         'yes'|'no'|'tie'|null    null when valid_votes == 0
     *  - winners        array<int,string>
     *  - passed         bool                     motion outcome (D5)
     *  - pass_threshold mixed                    the applied threshold, echoed back
     *
     * @param array<int, Vote> $votes
     * @return array<string, mixed>
     */
    public static function calculateResults(array $votes, BallotComponent $component, bool $abstainable = false): array
    {
        // D10 full roster: seed every declared option (yes, no) at 0, in order.
        /** @var array<string, int> $state */
        $state = [];
        /** @var list<string> $options */
        $options = self::optionList($component);
        foreach ($options as $option) {
            $state[$option] = 0;
        }

        $abstentions = 0;
        $invalid = 0;

        foreach ($votes as $vote) {
            $values = $vote->values;
            $hasAnswer = is_array($values) && array_key_exists($component->id, $values);
            $answer = $hasAnswer ? $values[$component->id] : null;

            // Missing answer (no entry / empty values): abstain when the election
            // is abstainable (D9), otherwise an invalid/blank non-vote.
            if (!$hasAnswer || $answer === null || $answer === '') {
                if ($abstainable) {
                    $abstentions++;
                } else {
                    $invalid++;
                }
                continue;
            }

            // A legitimate abstention token, only when abstainable (D9): counted
            // separately, never in state, never winnable.
            if ($abstainable && $answer === 'abstain') {
                $abstentions++;
                continue;
            }

            // Reconcile against the option roster (D9): a scalar yes/no is valid;
            // anything else (maybe/unknown, a stray abstain on a non-abstainable
            // election, a non-scalar/array) is invalid — never winnable.
            if (is_string($answer) && array_key_exists($answer, $state)) {
                $state[$answer]++;
            } else {
                $invalid++;
            }
        }

        return self::annotateStateForVictory($state, $abstentions, $invalid, $component);
    }

    /**
     * The ordered option roster for this component, falling back to the preset
     * yes/no list when the component carries no usable options.
     *
     * @return list<string>
     */
    private static function optionList(BallotComponent $component): array
    {
        $clean = [];
        foreach ($component->options as $option) {
            if (is_string($option)) {
                $clean[] = $option;
            }
        }
        if (count($clean) > 0) {
            return $clean;
        }
        return static::$presetOptions;
    }

    /**
     * @param array<string, int> $state
     * @return array<string, mixed>
     */
    public static function annotateStateForVictory(
        array $state,
        int $abstentions = 0,
        int $invalid = 0,
        ?BallotComponent $component = null
    ): array {
        $threshold = self::passThreshold($component);

        $yes = $state['yes'] ?? 0;
        $no = $state['no'] ?? 0;
        $validVotes = $yes + $no;
        $totalVotes = $validVotes + $abstentions + $invalid;

        // Winner = the leading valid side; 'tie' when level; null with no valid votes.
        if ($validVotes === 0) {
            $winner = null;
            $winners = [];
        } elseif ($yes === $no) {
            $winner = 'tie';
            $winners = ['yes', 'no'];
        } elseif ($yes > $no) {
            $winner = 'yes';
            $winners = ['yes'];
        } else {
            $winner = 'no';
            $winners = ['no'];
        }

        return [
            'state' => $state,
            'valid_votes' => $validVotes,
            'abstentions' => $abstentions,
            'invalid' => $invalid,
            'total_votes' => $totalVotes,
            'winner' => $winner,
            'winners' => $winners,
            'passed' => self::isPassed($yes, $no, $threshold),
            'pass_threshold' => $threshold,
        ];
    }

    /**
     * D5 pass rule (motion semantics):
     *   passed = (yes > no) AND (yes / (yes + no) >= threshold).
     * The strict-majority floor (yes > no) means a tie never carries and there is
     * never minority passage, regardless of a sub-50 threshold.
     *
     * Threshold forms:
     *   - numeric percent (50, 65, 70): yes/(yes+no) >= n/100, compared literally
     *     (so a typed 66.67 is NOT exact two-thirds — the 66.67 vs 66.666… caveat).
     *   - preset 'two_thirds':     yes*3 >= 2*(yes+no)   (exact integer rational).
     *   - preset 'three_quarters': yes*4 >= 3*(yes+no)   (exact integer rational).
     *
     * @param int|float|string $threshold
     */
    private static function isPassed(int $yes, int $no, $threshold): bool
    {
        $valid = $yes + $no;
        if ($valid === 0) {
            return false;
        }
        // Strict-majority floor — never carries on a tie or a minority.
        if ($yes <= $no) {
            return false;
        }

        if ($threshold === 'two_thirds') {
            return $yes * 3 >= 2 * $valid;
        }
        if ($threshold === 'three_quarters') {
            return $yes * 4 >= 3 * $valid;
        }

        // Numeric percent compared literally against the yes share.
        $percent = is_numeric($threshold) ? (float) $threshold : 50.0;
        return ($yes / $valid) * 100 >= $percent;
    }

    /**
     * Phase-1 stopgap: the pass threshold lives in the component's `settings`
     * payload (`settings.pass_threshold`), defaulting to 50 (simple majority).
     * The dedicated column/migration + GUI/CLI is Phase 2.
     *
     * @return int|float|string
     */
    private static function passThreshold(?BallotComponent $component): int|float|string
    {
        if ($component === null) {
            return 50;
        }
        /** @var mixed $settings */
        $settings = $component->getAttribute('settings');
        if (is_array($settings) && array_key_exists('pass_threshold', $settings)) {
            /** @var mixed $threshold */
            $threshold = $settings['pass_threshold'];
            if (is_int($threshold) || is_float($threshold) || is_string($threshold)) {
                return $threshold;
            }
        }
        return 50;
    }

    /**
     * Mirror the FPTP/Approval guard: return '' when the component key is absent,
     * otherwise the stored value RAW (machine-stable, never localized — the CSV
     * must stay parseable: yes / no / abstain).
     *
     * @param array<string, mixed> $values
     */
    public static function valuesToCsv(array $values, string $component_id): mixed
    {
        if (array_key_exists($component_id, $values)) {
            return $values[$component_id];
        }
        return '';
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
