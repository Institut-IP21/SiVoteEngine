<?php

namespace App\BallotComponents;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;

abstract class BallotComponentType
{
    /** @var bool */
    public static $livewireForm = false;

    /** @var bool */
    public static $needsOptions = false;

    /** @var array<array-key, mixed> */
    public static $optionsValidator = [];

    /** @return array<string, mixed> */
    abstract public static function strings(): array;

    /**
     * @param array<int, Vote> $votes
     * @param bool $abstainable Whether the election permits abstentions (lets the
     *   calculator tell a legitimate abstention from an invalid/out-of-options value).
     * @return array<string, mixed>
     */
    abstract public static function calculateResults(array $votes, BallotComponent $component, bool $abstainable = false): array;

    /** @return array<string, mixed> */
    abstract public static function getSubmissionValidator(BallotComponent $component, Election $election): array;

    /**
     * @param mixed $options
     */
    abstract public static function validateOptions($options): bool;

    /**
     * @param array<string, mixed> $values
     * @param string $component_id
     * @return mixed
     */
    public static function valuesToCsv(array $values, string $component_id): mixed
    {
        return $values[$component_id];
    }
}
