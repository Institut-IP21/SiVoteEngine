<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotActivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_activates_inactive_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Pending Ballot',
            'active' => false,
            'finished' => false,
        ]);

        $this->artisan('evote:activate:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain("Ballot 'Pending Ballot' has been activated")
            ->assertExitCode(0);

        $ballot->refresh();
        $this->assertTrue($ballot->active);
    }

    public function test_errors_on_finished_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Done Ballot',
            'active' => false,
            'finished' => true,
        ]);

        $this->artisan('evote:activate:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Cannot reactivate a finished ballot')
            ->assertExitCode(1);
    }

    public function test_idempotent_on_already_active(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Active Ballot',
            'active' => true,
            'finished' => false,
        ]);

        $this->artisan('evote:activate:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Ballot is already active')
            ->assertExitCode(0);
    }
}
