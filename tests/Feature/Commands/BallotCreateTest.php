<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.cli.default_owner' => 'test-owner-uuid']);
    }

    public function test_create_ballot_with_all_options(): void
    {
        $election = Election::factory()->create();

        $this->artisan('evote:make:ballot', [
            '--election' => $election->id,
            '--title' => 'Test Ballot',
            '--description' => 'Ballot description',
        ])
            ->expectsOutputToContain("Created new ballot titled 'Test Ballot'")
            ->assertExitCode(0);

        $this->assertDatabaseHas('ballots', [
            'election_id' => $election->id,
            'title' => 'Test Ballot',
            'description' => 'Ballot description',
        ]);
    }

    public function test_interactive_election_selection(): void
    {
        $election = Election::factory()->create([
            'title' => 'My Election',
            'owner' => 'test-owner-uuid',
        ]);

        $this->artisan('evote:make:ballot', [
            '--title' => 'Interactive Ballot',
            '--description' => 'Description',
        ])
            ->expectsChoice(
                'Please enter the ID of an existing election',
                'My Election',
                ['My Election']
            )
            ->expectsOutputToContain("Created new ballot titled 'Interactive Ballot'")
            ->assertExitCode(0);

        $this->assertDatabaseHas('ballots', [
            'election_id' => $election->id,
            'title' => 'Interactive Ballot',
        ]);
    }
}
