<?php

declare(strict_types=1);

namespace App\BallotComponents\Contracts;

use App\BallotComponents\DTOs\AcademicText;
use App\BallotComponents\DTOs\ComponentMetadata;
use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\StatuteText;
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
     * Get the bilingual statute/legal-reference clause text for this component
     * (static, admin-only reference material — see
     * local_docs/voting-components/statute-feature-spec.md).
     */
    public function getStatuteText(): StatuteText;

    /**
     * Get the bilingual academic/educational explainer text (neutral
     * explanation + pros/cons) for this component, migrated from web_app so
     * this content lives in one place (see `local_docs` migration note in
     * `AcademicText`).
     */
    public function getAcademicText(): AcademicText;

    /**
     * Convert vote values to CSV format for export.
     *
     * @param array<string, mixed> $values
     */
    public function valuesToCsv(array $values, string $componentId): string;
}
