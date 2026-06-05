<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

final readonly class ValidationRules
{
    /**
     * @param array<string, array<mixed>> $rules Laravel validation rules
     */
    public function __construct(
        public array $rules,
    ) {}

    /**
     * Convert to array for use with Laravel Validator.
     *
     * @return array<string, array<mixed>>
     */
    public function toArray(): array
    {
        return $this->rules;
    }
}
