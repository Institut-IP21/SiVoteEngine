<?php

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D11: the result page renders a "result not binding" banner and suppresses the
 * winner verdict when turnout < quorum; otherwise the normal result shows.
 */
class BallotQuorumRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A finished FPTP ballot where every cast vote picks "Ana" (a clear winner),
     * with $issued issued codes of which $cast are cast.
     */
    private function makeFinishedBallot(?int $quorum, int $issued, int $cast): array
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'finished' => true,
            'quorum' => $quorum,
        ]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => ['Ana', 'Betty'],
            'active' => true,
        ]);

        $componentId = $ballot->components[0]->id;
        for ($i = 0; $i < $issued; $i++) {
            $attrs = ['ballot_id' => $ballot->id];
            // `values` is Encryptable: omit it for uncast codes so the column stays
            // genuinely NULL (a set null would encrypt to a non-null ciphertext and
            // be miscounted as a cast vote).
            if ($i < $cast) {
                $attrs['values'] = [$componentId => 'Ana'];
            }
            Vote::factory()->create($attrs);
        }

        return [$election, $ballot->fresh()];
    }

    private function bannerText(Ballot $ballot): string
    {
        return __('ballot.quorum.not_met', [
            'turnout' => $ballot->votes_count,
            'quorum' => $ballot->quorum,
        ]);
    }

    public function test_quorum_failed_shows_banner_and_suppresses_winner(): void
    {
        // 5 issued, 2 cast, quorum 4 → not met.
        [$election, $ballot] = $this->makeFinishedBallot(4, 5, 2);

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertOk();
        // Prominent "result not binding" message.
        $response->assertSeeText($this->bannerText($ballot));
        // #6: a SINGLE quorum warning — the old separate red banner (border-red-400) is
        // gone; everything is now in the one panel (#3 tokenized it to border-danger).
        $response->assertDontSee('border-red-400', false);
        // Tallies still render (Ana row visible).
        $response->assertSeeText('Ana');
        // No winner highlight when quorum fails.
        $response->assertDontSee('winner bg-secure-soft', false);
    }

    public function test_quorum_met_shows_winner_and_no_banner(): void
    {
        // 5 issued, 4 cast, quorum 3 → met.
        [$election, $ballot] = $this->makeFinishedBallot(3, 5, 4);

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertOk();
        $response->assertDontSeeText($this->bannerText($ballot));
        // The winning option is highlighted.
        $response->assertSee('winner bg-secure-soft', false);
    }

    public function test_null_quorum_shows_winner_and_no_banner(): void
    {
        [$election, $ballot] = $this->makeFinishedBallot(null, 5, 1);

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertOk();
        $response->assertDontSeeText('result not binding');
        $response->assertSee('winner bg-secure-soft', false);
    }
}
