<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotComponentDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_component_successfully(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'title' => 'Old Component',
        ]);

        $this->artisan('evote:delete:ballot:component', ['--component' => $component->id])
            ->expectsConfirmation("Are you sure you want to delete component 'Old Component'?", 'yes')
            ->expectsOutputToContain("Component 'Old Component' has been deleted")
            ->assertExitCode(0);

        $this->assertDatabaseMissing('ballot_components', ['id' => $component->id]);
    }

    public function test_requires_confirmation(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'title' => 'Keep Component',
        ]);

        $this->artisan('evote:delete:ballot:component', ['--component' => $component->id])
            ->expectsConfirmation("Are you sure you want to delete component 'Keep Component'?", 'no')
            ->expectsOutputToContain('Cancelled')
            ->assertExitCode(0);

        $this->assertDatabaseHas('ballot_components', ['id' => $component->id]);
    }
}
