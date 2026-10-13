<?php

namespace Tests\Feature\Ballot;

use App\Http\Resources\BallotComplete;
use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotVotesAnonymousTest extends TestCase
{
    use RefreshDatabase;

    public function test_ballot_complete_resource_exposes_no_voting_code_or_metadata_and_is_sorted(): void
    {
        $el = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $el->id, 'finished' => true]);
        $ids = [];
        foreach (['c', 'a', 'b'] as $choice) {
            $ids[] = Vote::factory()->forBallot($ballot)->withValues(['q' => $choice])->create()->id;
        }
        Vote::factory()->forBallot($ballot)->create(); // uncast code

        // Rendered via the resource directly: the `ballot.api.votes` route is currently
        // shadowed by `vote.show` (same URI), so the resource has no live HTTP route.
        $out = (new BallotComplete($ballot))->resolve(request());

        $this->assertSame([['q' => 'a'], ['q' => 'b'], ['q' => 'c']], $out['cast_votes']);
        $json = (string) json_encode($out);
        foreach ($ids as $id) {
            $this->assertStringNotContainsString($id, $json);
        }
    }
}
