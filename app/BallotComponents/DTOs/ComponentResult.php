<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

interface ComponentResult
{
    /**
     * Serialize the result for the JSON results API and the Blade result views.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
