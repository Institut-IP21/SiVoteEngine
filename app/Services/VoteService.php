<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Vote;

class VoteService
{

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

    public function generatePublicVotes(Ballot $ballot, array $voters): array
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
            $codes[] = $vote->id;
        }
        return $codes;
    }
}
