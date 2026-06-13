<?php

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D11: per-ballot enforced quorum — the Ballot model accessors.
 */
class BallotQuorumTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a ballot with $issued issued Vote rows, of which $cast carry values
     * (i.e. are "cast" / counted in turnout).
     */
    private function makeBallot(?int $quorum, int $issued, int $cast): Ballot
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'quorum' => $quorum,
        ]);

        for ($i = 0; $i < $issued; $i++) {
            $attrs = ['ballot_id' => $ballot->id];
            // `values` is Encryptable: setting it (even to null) stores a non-null
            // ciphertext, which castVotes() would count as cast. An uncast/issued
            // code must leave the column genuinely NULL, so omit the key entirely.
            if ($i < $cast) {
                $attrs['values'] = ['answer' => 'x'];
            }
            Vote::factory()->create($attrs);
        }

        return $ballot->fresh();
    }

    public function test_quorum_met_is_true_when_quorum_is_null(): void
    {
        $ballot = $this->makeBallot(null, 5, 1);

        $this->assertTrue($ballot->quorum_met);
    }

    public function test_quorum_met_is_true_when_turnout_reaches_quorum(): void
    {
        // turnout == quorum (boundary)
        $ballot = $this->makeBallot(3, 5, 3);
        $this->assertSame(3, $ballot->votes_count);
        $this->assertTrue($ballot->quorum_met);

        // turnout > quorum
        $ballot = $this->makeBallot(3, 5, 4);
        $this->assertTrue($ballot->quorum_met);
    }

    public function test_quorum_met_is_false_when_turnout_below_quorum(): void
    {
        $ballot = $this->makeBallot(4, 5, 2);

        $this->assertSame(2, $ballot->votes_count);
        $this->assertFalse($ballot->quorum_met);
    }

    public function test_electorate_size_is_issued_code_count(): void
    {
        $ballot = $this->makeBallot(null, 7, 3);

        // 7 issued codes, regardless of how many were cast.
        $this->assertSame(7, $ballot->electorate_size);
        $this->assertSame(3, $ballot->votes_count);
    }
}
