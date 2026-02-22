<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_election_successfully(): void
    {
        $election = Election::factory()->create(['title' => 'Old Election']);

        $this->artisan('evote:delete:election', ['--election' => $election->id])
            ->expectsConfirmation("Are you sure you want to delete election 'Old Election'?", 'yes')
            ->expectsOutputToContain("Election 'Old Election' has been deleted")
            ->assertExitCode(0);

        $this->assertSoftDeleted('elections', ['id' => $election->id]);
    }

    public function test_blocks_if_active_ballots(): void
    {
        $election = Election::factory()->create(['title' => 'Active Election']);
        Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
        ]);

        $this->artisan('evote:delete:election', ['--election' => $election->id])
            ->expectsOutputToContain('Cannot delete an election with active ballots')
            ->assertExitCode(1);
    }

    public function test_requires_confirmation(): void
    {
        $election = Election::factory()->create(['title' => 'Keep Election']);

        $this->artisan('evote:delete:election', ['--election' => $election->id])
            ->expectsConfirmation("Are you sure you want to delete election 'Keep Election'?", 'no')
            ->expectsOutputToContain('Cancelled')
            ->assertExitCode(0);

        $this->assertDatabaseHas('elections', ['id' => $election->id, 'deleted_at' => null]);
    }
}
