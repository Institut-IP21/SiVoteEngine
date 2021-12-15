<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Facades\URL;

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
