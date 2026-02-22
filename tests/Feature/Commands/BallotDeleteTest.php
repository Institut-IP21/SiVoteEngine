<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_ballot_successfully(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Old Ballot',
            'active' => false,
            'finished' => true,
        ]);

        $this->artisan('evote:delete:ballot', ['--ballot' => $ballot->id])
            ->expectsConfirmation("Are you sure you want to delete ballot 'Old Ballot'?", 'yes')
            ->expectsOutputToContain("Ballot 'Old Ballot' has been deleted")
            ->assertExitCode(0);

        $this->assertSoftDeleted('ballots', ['id' => $ballot->id]);
    }

    public function test_blocks_if_active(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
        ]);

        $this->artisan('evote:delete:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Cannot delete an active ballot. Deactivate it first.')
            ->assertExitCode(1);
    }

    public function test_requires_confirmation(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Keep Ballot',
            'active' => false,
        ]);

        $this->artisan('evote:delete:ballot', ['--ballot' => $ballot->id])
            ->expectsConfirmation("Are you sure you want to delete ballot 'Keep Ballot'?", 'no')
            ->expectsOutputToContain('Cancelled')
            ->assertExitCode(0);

        $this->assertDatabaseHas('ballots', ['id' => $ballot->id, 'deleted_at' => null]);
    }
}
