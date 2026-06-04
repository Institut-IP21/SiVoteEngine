<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

interface ComponentResult
{
    /**
     * Convert to array for backward compatibility with existing views.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
