<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class VoteService
{
    /**
     * Run a code-minting closure at most once per ballot. Takes a row lock on the
     * ballot (ballot first, then vote rows: the same order as casting and
     * rollback), then refuses (returns null) if the ballot is finished or already
     * has any Vote row. Safe to retry on deadlock: the guard is re-evaluated.
     *
     * @template T
     * @param \Closure(Ballot): T $mint receives the freshly locked ballot
     * @return T|null null when codes already exist or the ballot is finished
     */
    public function mintOnce(Ballot $ballot, \Closure $mint): mixed
    {
        return DB::transaction(function () use ($ballot, $mint) {
            $locked = Ballot::whereKey($ballot->id)->lockForUpdate()->firstOrFail();

            if ($locked->finished || $locked->votes()->exists()) {
                return null;
            }

            return $mint($locked);
        }, attempts: 3);
    }

    /** @return array<int, string> */
    public function generateSecretVotes(Ballot $ballot, int $quantity): array
    {
        $codes = [];

        $now = date('Y-m-d H:i:s');
        for ($i = 0; $i < $quantity; $i++) {
            $vote = Vote::create(
                [
                    'ballot_id' => $ballot->id,
                    'created_at' => $now
                ]
            );
            $codes[] = $vote->id;
        }
        return $codes;
    }

    /**
     * @param array<int, string> $voters
     * @return array<int, array{code: string, voter: string, access_url: string}>
     */
    public function generatePublicVotes(Election $election, Ballot $ballot, array $voters): array
    {
        $codes = [];

        $now = date('Y-m-d H:i:s');

        foreach ($voters as $voter) {
            $vote = Vote::create(
                [
                    'ballot_id' => $ballot->id,
                    'created_at' => $now,
                    'cast_by' => $voter
                ]
            );
            $codes[] = [
                'code' => $vote->id,
                'voter' => $voter,
                'access_url' => URL::temporarySignedRoute('ballot.session', now()->addMinutes(120), ['election' => $election->id, 'ballot' => $ballot->id, 'code' => $vote->id])
            ];
        }
        return $codes;
    }
}
