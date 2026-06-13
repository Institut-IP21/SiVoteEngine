<?php

declare(strict_types=1);

namespace App\BallotComponents\YesNo\v1;

use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\SimpleVoteResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Yes/No ballot component.
 *
 * A simple binary choice component with preset options.
 */
final class YesNo extends AbstractBallotComponent
{
    /** @var array<string> */
    private const PRESET_OPTIONS = ['yes', 'no'];

    #[\Override]
    protected function needsOptions(): bool
    {
        return false;
    }

    #[\Override]
    protected function getPresetOptions(): array
    {
        return self::PRESET_OPTIONS;
    }

    #[\Override]
    protected function getStrings(): array
    {
        return [
            'name' => __('components.yesno.name'),
            'description' => __('components.yesno.description'),
        ];
    }

    #[\Override]
    protected function getOptionsValidatorRules(): array
    {
        return ['options' => 'in:yes,no'];
    }

    /**
     * Tally a single binary motion (D1/D4/D5/D9/D10).
     *
     * state seeds both yes/no at 0 (D10). valid_votes = yes + no (the % and pass
     * denominator). A legitimate `abstain` (abstainable only) or a missing/blank
     * answer is an abstention; anything else (maybe/unknown, a stray abstain on a
     * non-abstainable election, a non-scalar) is invalid and never winnable (D9).
     * `passed` applies the configurable pass threshold from settings (D5).
     */
    #[\Override]
    public function calculateResults(Collection $votes, BallotComponent $component, bool $abstainable = false): ComponentResult
    {
        // D10 full roster: seed every declared option (yes, no) at 0, in order.
        /** @var array<string, int> $state */
        $state = [];
        foreach ($this->optionList($component) as $option) {
            $state[$option] = 0;
        }

        $abstentions = 0;
        $invalid = 0;

        foreach ($votes as $vote) {
            $values = $vote->values;
            $hasAnswer = is_array($values) && array_key_exists($component->id, $values);
            $answer = $hasAnswer ? $values[$component->id] : null;

            // Missing / blank: abstention when abstainable (D9), else invalid.
            if (!$hasAnswer || $answer === null || $answer === '') {
                $abstainable ? $abstentions++ : $invalid++;
                continue;
            }

            // A legitimate abstain token, only when abstainable (D9).
            if ($abstainable && $answer === 'abstain') {
                $abstentions++;
                continue;
            }

            // Reconcile against the roster; everything else is invalid (D9).
            if (is_string($answer) && array_key_exists($answer, $state)) {
                $state[$answer]++;
            } else {
                $invalid++;
            }
        }

        $yes = $state['yes'] ?? 0;
        $no = $state['no'] ?? 0;
        $validVotes = $yes + $no;
        $totalVotes = $validVotes + $abstentions + $invalid;
        $threshold = $this->passThreshold($component);

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

        return new SimpleVoteResult(
            state: $state,
            totalVotes: $totalVotes,
            winner: $winner,
            winners: $winners,
            validVotes: $validVotes,
            abstentions: $abstentions,
            invalid: $invalid,
            passed: $this->isPassed($yes, $no, $threshold),
            passThreshold: $threshold,
        );
    }

    /**
     * The ordered option roster, falling back to the preset yes/no list when the
     * component carries no usable options.
     *
     * @return array<int, string>
     */
    private function optionList(BallotComponent $component): array
    {
        $clean = [];
        foreach (($component->options ?? []) as $option) {
            if (is_string($option)) {
                $clean[] = $option;
            }
        }
        return $clean !== [] ? $clean : self::PRESET_OPTIONS;
    }

    /**
     * D5 pass rule: passed = (yes > no) AND (yes share >= threshold). The strict
     * majority floor means a tie never carries and there is never minority passage.
     *  - 'two_thirds':     yes*3 >= 2*(yes+no)   (exact integer rational)
     *  - 'three_quarters': yes*4 >= 3*(yes+no)
     *  - numeric percent:  yes/(yes+no)*100 >= n (compared literally)
     */
    private function isPassed(int $yes, int $no, int|float|string $threshold): bool
    {
        $valid = $yes + $no;
        if ($valid === 0 || $yes <= $no) {
            return false;
        }
        if ($threshold === 'two_thirds') {
            return $yes * 3 >= 2 * $valid;
        }
        if ($threshold === 'three_quarters') {
            return $yes * 4 >= 3 * $valid;
        }
        $percent = is_numeric($threshold) ? (float) $threshold : 50.0;
        return ($yes / $valid) * 100 >= $percent;
    }

    /**
     * The pass threshold from the component's `settings` payload
     * (`settings.pass_threshold`), defaulting to 50 (simple majority).
     */
    private function passThreshold(BallotComponent $component): int|float|string
    {
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

    #[\Override]
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules
    {
        $options = self::PRESET_OPTIONS;
        if ($election->abstainable) {
            $options[] = 'abstain';
        }

        return new ValidationRules([
            $component->id => ['required', Rule::in($options)],
        ]);
    }
}
