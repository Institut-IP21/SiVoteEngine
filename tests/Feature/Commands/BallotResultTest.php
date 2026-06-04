<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_results_for_finished_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Finished Ballot',
            'active' => false,
            'finished' => true,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'title' => 'President Vote',
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => ['Alice', 'Bob'],
        ]);

        // Create cast votes with real values
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => [$component->id => 'Alice'],
        ]);
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => [$component->id => 'Alice'],
        ]);
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => [$component->id => 'Bob'],
        ]);

        $this->artisan('evote:result:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('President Vote')
            ->expectsOutputToContain('Alice')
            ->expectsOutputToContain('Bob')
            ->expectsOutputToContain('Total cast votes:')
            ->assertExitCode(0);
    }

    public function test_errors_on_unfinished_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
            'finished' => false,
        ]);

        $this->artisan('evote:result:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Results are only available for finished ballots')
            ->assertExitCode(1);
    }
}
