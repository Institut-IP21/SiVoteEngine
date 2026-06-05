<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

final readonly class ComponentMetadata
{
    /**
     * @param array<string, string> $strings Localized strings (name, description)
     * @param array<string, string> $optionsValidator Validation rules for options
     * @param array<string>|null $presetOptions Preset options for components that don't need custom options
     */
    public function __construct(
        public bool $needsOptions,
        public bool $livewireForm,
        public array $strings,
        public array $optionsValidator,
        public ?array $presetOptions = null,
    ) {}

    /**
     * Serialize the metadata for the component-tree API and Blade forms.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'needsOptions' => $this->needsOptions,
            'livewireForm' => $this->livewireForm,
            'optionsValidators' => $this->optionsValidator,
            'strings' => $this->strings,
        ];
    }
}
