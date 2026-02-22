<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotDeactivateTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivates_active_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Active Ballot',
            'active' => true,
            'finished' => false,
        ]);

        $this->artisan('evote:deactivate:ballot', ['--ballot' => $ballot->id])
            ->expectsConfirmation(
                "This will permanently close voting on ballot 'Active Ballot'. Continue?",
                'yes'
            )
            ->expectsOutputToContain("Ballot 'Active Ballot' has been deactivated")
            ->assertExitCode(0);

        $ballot->refresh();
        $this->assertEquals(false, $ballot->active);
        $this->assertEquals(true, $ballot->finished);
    }

    public function test_errors_on_not_active_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => false,
        ]);

        $this->artisan('evote:deactivate:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Ballot is not currently active')
            ->assertExitCode(1);
    }

    public function test_requires_confirmation(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Active Ballot',
            'active' => true,
            'finished' => false,
        ]);

        $this->artisan('evote:deactivate:ballot', ['--ballot' => $ballot->id])
            ->expectsConfirmation(
                "This will permanently close voting on ballot 'Active Ballot'. Continue?",
                'no'
            )
            ->expectsOutputToContain('Cancelled')
            ->assertExitCode(0);

        $ballot->refresh();
        $this->assertEquals(true, $ballot->active);
        $this->assertEquals(false, $ballot->finished);
    }
}
