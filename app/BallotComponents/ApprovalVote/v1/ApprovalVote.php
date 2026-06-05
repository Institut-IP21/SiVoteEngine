<?php

declare(strict_types=1);

namespace App\BallotComponents\ApprovalVote\v1;

use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\SimpleVoteResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
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
        // Approval ballots store an array of selections; tallyValues() counts
        // each selected option once (scalar values are treated as a single
        // selection).
        return SimpleVoteResult::fromTallies($this->tallyValues($votes, $component));
    }

    #[\Override]
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules
    {
        // The submission must be an array of options. Without the explicit
        // `array` rule a crafted scalar value bypasses the `.*` option
        // whitelist entirely and is stored verbatim. `distinct` prevents a
        // single ballot from approving the same option more than once.
        return new ValidationRules([
            $component->id => [$election->abstainable ? 'nullable' : 'required', 'array'],
            "{$component->id}.*" => ['distinct', Rule::in($component->options)],
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
