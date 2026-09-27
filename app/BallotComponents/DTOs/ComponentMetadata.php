<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

final readonly class ComponentMetadata
{
    /**
     * @param array<string, string|list<array{heading: string, body: string}>> $strings Localized strings (name, description, hint, lay_explanation are strings; lay_segments is a list), in the REQUEST locale — kept for back-compat
     * @param array<string, string> $optionsValidator Validation rules for options
     * @param array<string>|null $presetOptions Preset options for components that don't need custom options
     * @param string $cardinality How many results this component elects: 'single', 'multiple', or 'decision'
     * @param array{name: array{en: string, sl: string}, method: array{en: string, sl: string}} $i18n
     *   Both-locale name/method, resolved explicitly (never the request locale) — for the
     *   add-question modal's locale switcher, which needs both without a second round trip.
     */
    public function __construct(
        public bool $needsOptions,
        public bool $livewireForm,
        public array $strings,
        public array $optionsValidator,
        public ?array $presetOptions = null,
        public string $cardinality = 'single',
        public array $i18n = ['name' => ['en' => '', 'sl' => ''], 'method' => ['en' => '', 'sl' => '']],
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
            'cardinality' => $this->cardinality,
            'i18n' => $this->i18n,
        ];
    }
}
