<?php

declare(strict_types=1);

namespace App\BallotComponents\ApprovalVote\v1;

use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
use App\BallotComponents\Traits\CalculatesSimpleVictory;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Approval Vote ballot component.
 *
 * Multiple selection voting where voters can approve multiple options.
 */
final class ApprovalVote extends AbstractBallotComponent
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
            'name' => __('components.approval.name'),
            'description' => __('components.approval.description'),
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
            ->filter(fn ($vote): bool => !empty($vote->values))
            ->groupBy(function ($vote) use ($component): string {
                return $vote->values[$component->id] ?? 'abstain';
            })
            ->map(fn (Collection $group): int => $group->count())
            ->toArray();

        return $this->calculateVictory($tallies);
    }

    #[\Override]
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules
    {
        return new ValidationRules([
            $component->id => [$election->abstainable ? 'nullable' : 'required'],
            "{$component->id}.*" => [Rule::in($component->options)],
        ]);
    }

    #[\Override]
    public function valuesToCsv(array $values, string $componentId): string
    {
        if (!array_key_exists($componentId, $values)) {
            return '';
        }

        $value = $values[$componentId];
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }
}
