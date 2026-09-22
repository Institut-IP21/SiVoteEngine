<?php

namespace Tests\Feature\Security;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoteSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Election, 1: Ballot, 2: array<int, string>} */
    private function finishedBallotWithVotes(int $count = 3): array
    {
        $election = Election::factory()->create(['owner' => (string) Str::uuid()]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => true,
        ]);
        BallotComponent::factory()->create(['ballot_id' => $ballot->id]);

        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $vote = Vote::factory()->create([
                'ballot_id' => $ballot->id,
                'values' => ['choice' => 'yes'],
            ]);
            $ids[] = $vote->id;
        }

        return [$election, $ballot, $ids];
    }

    public function test_deleting_ballot_soft_deletes_votes_and_components(): void
    {
        [, $ballot, $ids] = $this->finishedBallotWithVotes();

        $ballot->delete();

        foreach ($ids as $id) {
            $this->assertSoftDeleted('votes', ['id' => $id]);
            $this->assertNotNull(Vote::withTrashed()->find($id), 'Vote must be recoverable.');
            $this->assertNull(Vote::find($id), 'Vote must be hidden from normal queries.');
        }

        $this->assertSame(0, BallotComponent::where('ballot_id', $ballot->id)->count());
        $this->assertSame(1, BallotComponent::withTrashed()->where('ballot_id', $ballot->id)->count());

        $this->assertSame(count($ids), Vote::withTrashed()->where('ballot_id', $ballot->id)->count());
    }

    public function test_deleting_election_soft_deletes_votes(): void
    {
        [$election, $ballot, $ids] = $this->finishedBallotWithVotes();

        $election->delete();

        foreach ($ids as $id) {
            $this->assertSoftDeleted('votes', ['id' => $id]);
        }
        $this->assertSame(count($ids), Vote::withTrashed()->where('ballot_id', $ballot->id)->count());
    }

    public function test_trashed_votes_are_excluded_from_tallies(): void
    {
        [, $ballot, $ids] = $this->finishedBallotWithVotes();

        $this->assertSame(3, $ballot->votes_count);
        $this->assertSame(3, $ballot->electorate_size);

        Vote::find($ids[0])?->delete();

        $ballot->refresh();
        $this->assertSame(2, $ballot->votes_count);
        $this->assertSame(2, $ballot->electorate_size);
    }
}
