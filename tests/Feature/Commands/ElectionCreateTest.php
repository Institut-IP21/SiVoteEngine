<?php

namespace Tests\Feature\Commands;

use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.cli.default_owner' => 'test-owner-uuid']);
    }

    public function test_create_election_with_all_options(): void
    {
        $this->artisan('evote:make:election', [
            '--title' => 'Board Election',
            '--description' => 'Annual board election',
            '--abstainable' => 'yes',
            '--level' => '2',
        ])
            ->expectsOutputToContain("Created Election 'Board Election'")
            ->assertExitCode(0);

        $this->assertDatabaseHas('elections', [
            'title' => 'Board Election',
            'owner' => 'test-owner-uuid',
            'level' => 2,
        ]);
    }

    public function test_create_election_with_interactive_prompts(): void
    {
        // Command order: title check → description check → abstainable check → level check
        $this->artisan('evote:make:election')
            ->expectsQuestion('Please provide a title for the election', 'Interactive Election')
            ->expectsQuestion('Optionally provide a description for the election', 'A test description')
            ->expectsConfirmation('Can the voters abstain on questions in this election?', 'no')
            ->expectsChoice(
                'What level of security should this election have?',
                'Level 2 - Medium level of security. Your organization only needs one voting committee to operate this election.',
                [
                    '2' => 'Level 2 - Medium level of security. Your organization only needs one voting committee to operate this election.',
                    '3' => 'Level 3 - High level of security. Your organization needs one voting committee to operate this election, as well operate the Proxy on your own infrastructure.',
                ]
            )
            ->expectsOutputToContain("Created Election 'Interactive Election'")
            ->assertExitCode(0);

        $election = Election::where('title', 'Interactive Election')->first();
        $this->assertNotNull($election);
        $this->assertEquals('test-owner-uuid', $election->owner);
    }

    public function test_sets_default_owner_from_config(): void
    {
        config(['app.cli.default_owner' => 'custom-owner-id']);

        $this->artisan('evote:make:election', [
            '--title' => 'Owner Test',
            '--description' => 'desc',
            '--abstainable' => 'no',
            '--level' => '2',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('elections', [
            'title' => 'Owner Test',
            'owner' => 'custom-owner-id',
        ]);
    }
}
