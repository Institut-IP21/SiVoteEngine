<?php

declare(strict_types=1);

namespace App\BallotComponents\FirstPastThePost\v1;

use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\SimpleVoteResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * First Past The Post ballot component.
 *
 * Single selection voting where the option with the most votes wins.
 */
final class FirstPastThePost extends AbstractBallotComponent
{
    #[\Override]
    protected function needsOptions(): bool
    {
        return true;
    }

    #[\Override]
    protected function getStrings(): array
    {
        return [
            'name' => __('components.fptp.name'),
            'description' => __('components.fptp.description'),
            'hint' => __('components.fptp.hint'),
        ];
    }

    #[\Override]
    protected function getOptionsValidatorRules(): array
    {
        return [
            'options' => 'bail|required|array|min:2',
            'options.*' => 'bail|required|string|distinct|min:1',
        ];
    }

    /** The literal token a voter's stored answer carries for a deliberate abstention. */
    private const ABSTAIN = 'abstain';

    /**
     * Single-selection plurality tally (D1/D9/D10). state seeds the full roster
     * at 0 (D10). A scalar answer matching an option is a valid vote; a missing
     * answer or the abstain token is an abstention when abstainable, else invalid;
     * an unknown label or a non-scalar is invalid and never winnable (D9).
     */
    #[\Override]
    public function calculateResults(Collection $votes, BallotComponent $component, bool $abstainable = false): ComponentResult
    {
        /** @var array<string, int> $state */
        $state = [];
        foreach (($component->options ?? []) as $option) {
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

            if ($answer === null) {
                $abstainable ? $abstentions++ : $invalid++;
                continue;
            }
            if ($answer === '') {
                // empty string is never a legitimate abstention token (D9).
                $invalid++;
                continue;
            }
            if (!is_scalar($answer)) {
                $invalid++;
                continue;
            }

            $answer = (string) $answer;

            if ($answer === self::ABSTAIN) {
                $abstainable ? $abstentions++ : $invalid++;
                continue;
            }

            if (array_key_exists($answer, $state)) {
                $state[$answer]++;
            } else {
                $invalid++;
            }
        }

        return SimpleVoteResult::fromState($state, $abstentions, $invalid);
    }

    #[\Override]
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules
    {
        $options = $component->options;
        if ($election->abstainable) {
            $options[] = 'abstain';
        }

        return new ValidationRules([
            $component->id => ['required', Rule::in($options)],
        ]);
    }
}
