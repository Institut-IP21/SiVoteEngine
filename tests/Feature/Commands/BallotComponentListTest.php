<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotComponentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_all_components(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        BallotComponent::factory()->create(['ballot_id' => $ballot->id]);

        $this->artisan('evote:list:ballot:component')
            ->expectsOutputToContain('Displaying all Components')
            ->assertExitCode(0);
    }

    public function test_filter_by_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        BallotComponent::factory()->create(['ballot_id' => $ballot->id]);

        $other = Ballot::factory()->create(['election_id' => $election->id]);
        BallotComponent::factory()->create(['ballot_id' => $other->id]);

        $this->artisan('evote:list:ballot:component', ['--ballot' => $ballot->id])
            ->expectsOutputToContain("Displaying Component of Ballot {$ballot->id}")
            ->assertExitCode(0);
    }
}
