<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotCodesGenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_codes_with_valid_options(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);

        $this->artisan('evote:make:ballot:codes', [
            '--ballot' => $ballot->id,
            '--quantity' => 5,
        ])->assertExitCode(0);

        $this->assertEquals(5, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_interactive_fallback(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);

        $this->artisan('evote:make:ballot:codes')
            ->expectsQuestion('Please enter the ID of an existing ballot', $ballot->id)
            ->expectsQuestion('Please enter the number of codes to generate', '3')
            ->assertExitCode(0);

        $this->assertEquals(3, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_creates_correct_count_of_vote_records(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);

        $this->artisan('evote:make:ballot:codes', [
            '--ballot' => $ballot->id,
            '--quantity' => 10,
        ])->assertExitCode(0);

        $votes = Vote::where('ballot_id', $ballot->id)->get();
        $this->assertCount(10, $votes);

        // All votes should have null values (uncast)
        foreach ($votes as $vote) {
            $this->assertNull($vote->values);
        }
    }
}
