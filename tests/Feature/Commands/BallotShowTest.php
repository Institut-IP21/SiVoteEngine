<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_ballot_details(): void
    {
        $election = Election::factory()->create(['title' => 'Parent Election']);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Main Ballot',
            'active' => false,
            'finished' => false,
        ]);

        $this->artisan('evote:show:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Main Ballot')
            ->expectsOutputToContain('Parent Election')
            ->assertExitCode(0);
    }

    public function test_shows_components(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'title' => 'Question One',
            'type' => 'FirstPastThePost',
            'version' => 'v1',
        ]);

        $this->artisan('evote:show:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Question One')
            ->expectsOutputToContain('Components:')
            ->assertExitCode(0);
    }

    public function test_errors_on_nonexistent_ballot(): void
    {
        $this->artisan('evote:show:ballot', ['--ballot' => 'nonexistent-id'])
            ->expectsOutputToContain('No ballots found.')
            ->assertExitCode(1);
    }
}
