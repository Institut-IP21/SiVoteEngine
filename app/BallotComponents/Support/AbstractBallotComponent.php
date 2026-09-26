<?php

declare(strict_types=1);

namespace App\BallotComponents\Support;

use App\BallotComponents\Contracts\BallotComponentInterface;
use App\BallotComponents\DTOs\ComponentMetadata;
use App\BallotComponents\DTOs\StatuteText;
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
     * Get this component's statute/legal-reference clause paragraphs, in BOTH
     * locales explicitly (statute-feature-spec.md §2.2) — implementations must
     * use the 3-arg `trans($key, [], 'en'|'sl')`, never `__()`/`trans($key)`,
     * since those resolve against the request's locale and would return the
     * same language for both slots when called from a non-EN/SL request.
     *
     * @return array{en: list<string>, sl: list<string>}
     */
    abstract protected function getStatuteTextParagraphs(): array;

    /**
     * Fetch an ordered list of clause paragraphs from a `statute.php` key, in
     * an EXPLICIT locale (never the request locale — see
     * `getStatuteTextParagraphs()`). `trans()`'s static return type is
     * `array|string`; this narrows/validates it back to `list<string>` at
     * runtime (dropping anything that isn't a string, defensively) so the
     * abstract contract's `list<string>` promise holds for PHPStan too.
     *
     * @return list<string>
     */
    final protected function statuteParagraphs(string $key, string $locale): array
    {
        $value = trans($key, [], $locale);
        if (!is_array($value)) {
            return [];
        }

        $paragraphs = [];
        foreach ($value as $paragraph) {
            if (is_string($paragraph)) {
                $paragraphs[] = $paragraph;
            }
        }

        return $paragraphs;
    }

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

    /**
     * How many results this component elects — for the type-picker label.
     * One of 'single' (one winner), 'multiple' (several), or 'decision'
     * (a yes/no proposition, not a winner election).
     */
    protected function cardinality(): string
    {
        return 'single';
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
            cardinality: $this->cardinality(),
        );
    }

    /**
     * Package this component's bilingual clause paragraphs into the sealed
     * `StatuteText` DTO, mirroring how `getMetadata()` packages `getStrings()`.
     * `type` is derived from the concrete class's short name, which is exactly
     * the engine's stored `type` value for every registered component
     * (`YesNo`, `FirstPastThePost`, `RankedChoice`, `ApprovalVote`, `OrderedList`).
     */
    #[\Override]
    public function getStatuteText(): StatuteText
    {
        $paragraphs = $this->getStatuteTextParagraphs();

        return new StatuteText(
            type: class_basename(static::class),
            en: $paragraphs['en'],
            sl: $paragraphs['sl'],
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
