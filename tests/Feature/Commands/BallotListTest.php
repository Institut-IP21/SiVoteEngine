<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_all_ballots(): void
    {
        $election = Election::factory()->create();
        Ballot::factory()->create(['election_id' => $election->id, 'title' => 'Ballot A']);
        Ballot::factory()->create(['election_id' => $election->id, 'title' => 'Ballot B']);

        $this->artisan('evote:list:ballot')
            ->expectsOutputToContain('Displaying all Ballots')
            ->assertExitCode(0);
    }

    public function test_filter_by_election(): void
    {
        $election = Election::factory()->create();
        Ballot::factory()->create(['election_id' => $election->id, 'title' => 'Filtered Ballot']);

        $other = Election::factory()->create();
        Ballot::factory()->create(['election_id' => $other->id, 'title' => 'Other Ballot']);

        $this->artisan('evote:list:ballot', ['--election' => $election->id])
            ->expectsOutputToContain("Displaying Ballots for Election {$election->id}")
            ->assertExitCode(0);
    }

    public function test_invalid_election_shows_all(): void
    {
        $election = Election::factory()->create();
        Ballot::factory()->create(['election_id' => $election->id]);

        $this->artisan('evote:list:ballot', ['--election' => 'nonexistent-id'])
            ->expectsOutputToContain('Displaying all Ballots')
            ->assertExitCode(0);
    }
}
