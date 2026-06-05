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

    #[\Override]
    public function calculateResults(Collection $votes, BallotComponent $component): ComponentResult
    {
        return SimpleVoteResult::fromTallies($this->tallyValues($votes, $component));
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
