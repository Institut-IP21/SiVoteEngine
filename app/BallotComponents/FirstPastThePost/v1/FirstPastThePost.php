<?php

declare(strict_types=1);

namespace App\BallotComponents\FirstPastThePost\v1;

use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
use App\BallotComponents\Traits\CalculatesSimpleVictory;
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
    use CalculatesSimpleVictory;

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
        $tallies = $votes
            ->filter(fn ($vote): bool => !empty($vote->values) && isset($vote->values[$component->id]))
            ->groupBy(fn ($vote): string => $vote->values[$component->id])
            ->map(fn (Collection $group): int => $group->count())
            ->toArray();

        return $this->calculateVictory($tallies);
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
