<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_election_details(): void
    {
        $election = Election::factory()->create([
            'title' => 'Annual Election',
            'description' => 'Annual board election',
            'level' => 2,
        ]);

        $this->artisan('evote:show:election', ['--election' => $election->id])
            ->expectsOutputToContain('Annual Election')
            ->expectsOutputToContain('Annual board election')
            ->assertExitCode(0);
    }

    public function test_shows_ballots_sub_table(): void
    {
        $election = Election::factory()->create(['title' => 'Test Election']);
        Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'First Ballot',
            'active' => false,
            'finished' => false,
        ]);
        Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Second Ballot',
            'active' => true,
            'finished' => false,
        ]);

        $this->artisan('evote:show:election', ['--election' => $election->id])
            ->expectsOutputToContain('First Ballot')
            ->expectsOutputToContain('Second Ballot')
            ->expectsOutputToContain('Ballots:')
            ->assertExitCode(0);
    }

    public function test_errors_on_nonexistent_election_with_no_elections(): void
    {
        $this->artisan('evote:show:election', ['--election' => 'nonexistent-id'])
            ->expectsOutputToContain('No elections found.')
            ->assertExitCode(1);
    }

    public function test_interactive_selection_when_invalid_id(): void
    {
        $election = Election::factory()->create(['title' => 'Only Election']);

        $this->artisan('evote:show:election', ['--election' => 'bad-id'])
            ->expectsChoice('Select an election', 'Only Election', ['Only Election'])
            ->expectsOutputToContain('Only Election')
            ->assertExitCode(0);
    }
}
