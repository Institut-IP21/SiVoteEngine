<?php

namespace Tests\Feature\Voting;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VotePersistenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an election with a ballot and components for testing.
     */
    protected function createElectionWithBallot(array $componentConfigs = [], array $electionAttrs = [], array $ballotAttrs = []): array
    {
        $election = Election::factory()->create(array_merge([
            'abstainable' => false,
        ], $electionAttrs));

        $ballot = Ballot::factory()->create(array_merge([
            'election_id' => $election->id,
            'active' => true,
            'mode' => Ballot::MODE_BASIC,
        ], $ballotAttrs));

        $components = [];
        foreach ($componentConfigs as $config) {
            $components[] = BallotComponent::factory()->create(array_merge([
                'ballot_id' => $ballot->id,
            ], $config));
        }

        return [$election, $ballot, $components];
    }

    /**
     * Test that YesNo vote values are stored correctly in the database.
     */
    public function test_vote_values_stored_correctly_yesno(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve the proposal?', 'options' => []],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        // Submit vote
        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);

        $response->assertStatus(200);

        // Verify stored values
        $vote->refresh();
        $this->assertIsArray($vote->values);
        $this->assertEquals('yes', $vote->values[$component->id]);
    }

    /**
     * Test that FirstPastThePost vote values are stored correctly.
     */
    public function test_vote_values_stored_correctly_fptp(): void
    {
        $options = ['Alice', 'Bob', 'Charlie'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'FirstPastThePost', 'version' => 'v1', 'title' => 'Choose a candidate', 'options' => $options],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'Bob',
        ]);

        $response->assertStatus(200);

        $vote->refresh();
        $this->assertEquals('Bob', $vote->values[$component->id]);
    }

    /**
     * Test that ApprovalVote array values are stored correctly.
     */
    public function test_vote_values_stored_correctly_approval(): void
    {
        $options = ['Option A', 'Option B', 'Option C', 'Option D'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'ApprovalVote', 'version' => 'v1', 'title' => 'Select all that apply', 'options' => $options],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => ['Option A', 'Option C'],
        ]);

        $response->assertStatus(200);

        $vote->refresh();
        $this->assertIsArray($vote->values[$component->id]);
        $this->assertContains('Option A', $vote->values[$component->id]);
        $this->assertContains('Option C', $vote->values[$component->id]);
        $this->assertNotContains('Option B', $vote->values[$component->id]);
    }

    /**
     * Test that RankedChoice ordered array values are stored correctly.
     */
    public function test_vote_values_stored_correctly_ranked(): void
    {
        $options = ['First', 'Second', 'Third'];

        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'RankedChoice', 'version' => 'v1', 'title' => 'Rank your preferences', 'options' => $options],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => ['Third', 'First', 'Second'],
        ]);

        $response->assertStatus(200);

        $vote->refresh();
        $this->assertIsArray($vote->values[$component->id]);
        // Verify order is preserved
        $this->assertEquals(['Third', 'First', 'Second'], $vote->values[$component->id]);
    }

    /**
     * Test that subsequent vote submissions overwrite previous values.
     */
    public function test_vote_overwrites_on_resubmission(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        // First submission
        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);

        $vote->refresh();
        $this->assertEquals('yes', $vote->values[$component->id]);

        // Second submission - should overwrite
        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'no',
        ]);

        $vote->refresh();
        $this->assertEquals('no', $vote->values[$component->id]);
    }

    /**
     * Test that vote values are encrypted at rest in the database.
     */
    public function test_vote_values_encrypted_at_rest(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);

        // Get raw database value (bypassing Eloquent)
        $rawVote = DB::table('votes')->where('id', $vote->id)->first();

        // The raw value should NOT be readable as JSON (it's encrypted)
        $this->assertNotNull($rawVote->values);
        $this->assertNotEquals('{"' . $component->id . '":"yes"}', $rawVote->values);

        // Should be able to decrypt it
        $decrypted = Crypt::decrypt($rawVote->values);
        $this->assertIsArray($decrypted);
        $this->assertEquals('yes', $decrypted[$component->id]);
    }

    /**
     * Test that multiple voters' votes are stored independently.
     */
    public function test_multiple_voters_votes_stored_independently(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ]);

        $component = $components[0];

        $vote1 = Vote::factory()->forBallot($ballot)->create();
        $vote2 = Vote::factory()->forBallot($ballot)->create();
        $vote3 = Vote::factory()->forBallot($ballot)->create();

        // Submit different votes
        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote1->id,
            $component->id => 'yes',
        ]);

        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote2->id,
            $component->id => 'no',
        ]);

        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote3->id,
            $component->id => 'yes',
        ]);

        // Verify each vote is stored independently
        $vote1->refresh();
        $vote2->refresh();
        $vote3->refresh();

        $this->assertEquals('yes', $vote1->values[$component->id]);
        $this->assertEquals('no', $vote2->values[$component->id]);
        $this->assertEquals('yes', $vote3->values[$component->id]);
    }

    /**
     * Test that secret ballot votes have no cast_by identifier.
     */
    public function test_secret_ballot_vote_has_no_cast_by(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['is_secret' => true]);

        $component = $components[0];
        $vote = Vote::factory()->forBallot($ballot)->create();

        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);

        $vote->refresh();
        $this->assertNull($vote->cast_by);
    }

    /**
     * Test that public ballot votes can have cast_by identifier.
     */
    public function test_public_ballot_vote_has_cast_by(): void
    {
        [$election, $ballot, $components] = $this->createElectionWithBallot([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Approve?', 'options' => []],
        ], [], ['is_secret' => false]);

        $component = $components[0];
        $vote = Vote::factory()
            ->forBallot($ballot)
            ->castBy('voter@example.com')
            ->create();

        $this->post("/election/{$election->id}/ballot/{$ballot->id}", [
            'code' => $vote->id,
            $component->id => 'yes',
        ]);

        $vote->refresh();
        $this->assertEquals('voter@example.com', $vote->cast_by);
    }
}
