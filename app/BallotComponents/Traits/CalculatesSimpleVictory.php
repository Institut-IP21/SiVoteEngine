<?php

declare(strict_types=1);

namespace App\BallotComponents\Traits;

use App\BallotComponents\DTOs\SimpleVoteResult;

/**
 * Trait for ballot components that use simple plurality voting.
 *
 * This trait provides the shared victory calculation logic used by
 * YesNo, FirstPastThePost, and ApprovalVote components.
 */
trait CalculatesSimpleVictory
{
    /**
     * Calculate victory from vote tallies.
     *
     * @param array<string, int> $tallies Map of option => vote count
     */
    protected function calculateVictory(array $tallies): SimpleVoteResult
    {
        return SimpleVoteResult::fromTallies($tallies);
    }
}
