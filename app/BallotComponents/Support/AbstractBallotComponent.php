<?php

declare(strict_types=1);

namespace App\BallotComponents\Support;

use App\BallotComponents\Contracts\BallotComponentInterface;
use App\BallotComponents\DTOs\ComponentMetadata;
use App\Models\BallotComponent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * Base class for ballot components with common functionality.
 */
abstract class AbstractBallotComponent implements BallotComponentInterface
{
    /**
     * Whether this component requires custom options.
     */
    abstract protected function needsOptions(): bool;

    /**
     * Whether this component uses a Livewire form.
     */
    protected function usesLivewireForm(): bool
    {
        return false;
    }

    /**
     * Get localized strings for this component.
     *
     * @return array<string, string>
     */
    abstract protected function getStrings(): array;

    /**
     * Get validation rules for component options.
     *
     * @return array<string, string>
     */
    abstract protected function getOptionsValidatorRules(): array;

    /**
     * Get preset options (for components that don't need custom options).
     *
     * @return array<string>|null
     */
    protected function getPresetOptions(): ?array
    {
        return null;
    }

    #[\Override]
    public function getMetadata(): ComponentMetadata
    {
        return new ComponentMetadata(
            needsOptions: $this->needsOptions(),
            livewireForm: $this->usesLivewireForm(),
            strings: $this->getStrings(),
            optionsValidator: $this->getOptionsValidatorRules(),
            presetOptions: $this->getPresetOptions(),
        );
    }

    #[\Override]
    public function validateOptions(array $options): bool
    {
        $validator = Validator::make(
            ['options' => $options],
            $this->getOptionsValidatorRules()
        );

        return $validator->errors()->isEmpty();
    }

    #[\Override]
    public function valuesToCsv(array $values, string $componentId): string
    {
        return $values[$componentId] ?? '';
    }

    /**
     * Tally the values cast for this component into an option => count map.
     *
     * Handles both single-selection components (a scalar value) and
     * multi-selection components such as ApprovalVote (an array of values):
     * a scalar is treated as a single-element selection. Votes that did not
     * answer this component (null/missing value) are skipped.
     *
     * Every defined option is included in the result (seeded at 0) so that
     * options which received no votes still appear in the published results —
     * omitting them would hide candidates/choices from the tally and harm
     * transparency. When not a single vote was cast for this component an empty
     * map is returned so callers can distinguish "no result yet" from a
     * genuine all-zero tally. Selections outside the defined options (e.g. the
     * dynamically-added "abstain") are preserved.
     *
     * @param Collection<int, \App\Models\Vote> $votes
     * @return array<string, int> Map of option => vote count
     */
    protected function tallyValues(Collection $votes, BallotComponent $component): array
    {
        $counts = [];

        foreach ($votes as $vote) {
            $value = $vote->values[$component->id] ?? null;
            if ($value === null) {
                continue;
            }

            foreach ((array) $value as $selection) {
                $counts[$selection] = ($counts[$selection] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return [];
        }

        // Seed every defined option at 0 (preserving definition order), then
        // overlay the actual counts; extra selections such as "abstain" remain.
        $tallies = array_fill_keys($component->options ?? [], 0);
        foreach ($counts as $selection => $count) {
            $tallies[$selection] = $count;
        }

        return $tallies;
    }
}
