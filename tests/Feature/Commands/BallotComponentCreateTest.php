<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotComponentCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_fptp_component_with_all_options(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);

        $args = [
            'Ballot ID' => $ballot->id,
            'Title' => 'President Vote',
            'Description' => 'Vote for president',
            'Component Type' => 'FirstPastThePost',
            'Version' => 'v1',
            'Options' => ['Alice', 'Bob', 'Charlie'],
            'Settings' => null,
        ];
        $confirmText = "Please confirm the component: " . print_r($args, true);

        $this->artisan('evote:make:ballot:component', [
            '--ballot' => $ballot->id,
            '--title' => 'President Vote',
            '--description' => 'Vote for president',
            '--type' => 'FirstPastThePost',
            '--variant' => 'v1',
            '--options' => 'Alice,Bob,Charlie',
        ])
            ->expectsConfirmation($confirmText, 'yes')
            ->expectsOutputToContain('Created new FirstPastThePost:v1 component titled President Vote')
            ->assertExitCode(0);

        $this->assertDatabaseHas('ballot_components', [
            'ballot_id' => $ballot->id,
            'title' => 'President Vote',
            'type' => 'FirstPastThePost',
            'version' => 'v1',
        ]);
    }

    public function test_variant_option_works(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);

        $args = [
            'Ballot ID' => $ballot->id,
            'Title' => 'Board Members',
            'Description' => 'Elect board members',
            'Component Type' => 'ApprovalVote',
            'Version' => 'v1',
            'Options' => ['Alice', 'Bob'],
            'Settings' => null,
        ];
        $confirmText = "Please confirm the component: " . print_r($args, true);

        $this->artisan('evote:make:ballot:component', [
            '--ballot' => $ballot->id,
            '--title' => 'Board Members',
            '--description' => 'Elect board members',
            '--type' => 'ApprovalVote',
            '--variant' => 'v1',
            '--options' => 'Alice,Bob',
        ])
            ->expectsConfirmation($confirmText, 'yes')
            ->expectsOutputToContain('Created new ApprovalVote:v1 component')
            ->assertExitCode(0);

        $this->assertDatabaseHas('ballot_components', [
            'ballot_id' => $ballot->id,
            'type' => 'ApprovalVote',
            'version' => 'v1',
        ]);
    }
}
