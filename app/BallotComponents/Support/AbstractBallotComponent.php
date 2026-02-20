<?php

declare(strict_types=1);

namespace App\BallotComponents\Support;

use App\BallotComponents\Contracts\BallotComponentInterface;
use App\BallotComponents\DTOs\ComponentMetadata;
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
}
