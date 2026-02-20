<?php

declare(strict_types=1);

namespace App\BallotComponents\Enums;

enum VoteOutcome: string
{
    case Winner = 'winner';
    case Tie = 'tie';
    case NoVotes = 'no_votes';
}
