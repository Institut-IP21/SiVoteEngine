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

    #[\Override]
    public function calculateResults(Collection $votes, BallotComponent $component): ComponentResult
    {
        return SimpleVoteResult::fromTallies($this->tallyValues($votes, $component));
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
