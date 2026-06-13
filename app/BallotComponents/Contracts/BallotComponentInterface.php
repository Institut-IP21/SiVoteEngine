<?php

declare(strict_types=1);

namespace App\BallotComponents\Contracts;

use App\BallotComponents\DTOs\ComponentMetadata;
use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Collection;

interface BallotComponentInterface
{
    /**
     * Calculate the results for this component based on cast votes.
     *
     * @param Collection<int, \App\Models\Vote> $votes
     * @param bool $abstainable Whether the election permits abstentions (lets the
     *   calculator tell a legitimate abstention from an invalid/out-of-options value, D9).
     */
    public function calculateResults(Collection $votes, BallotComponent $component, bool $abstainable = false): ComponentResult;

    /**
     * Get validation rules for vote submission.
     */
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules;

    /**
     * Validate component options configuration.
     *
     * @param array<string> $options
     */
    public function validateOptions(array $options): bool;

    /**
     * Get component metadata (name, description, configuration requirements).
     */
    public function getMetadata(): ComponentMetadata;

    /**
     * Convert vote values to CSV format for export.
     *
     * @param array<string, mixed> $values
     */
    public function valuesToCsv(array $values, string $componentId): string;
}
