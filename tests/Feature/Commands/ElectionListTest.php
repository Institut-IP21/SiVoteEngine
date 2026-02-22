<?php

namespace Tests\Feature\Commands;

use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_empty_table_when_no_elections(): void
    {
        $this->artisan('evote:list:election')
            ->assertExitCode(0);
    }

    public function test_displays_existing_elections(): void
    {
        $election = Election::factory()->create(['title' => 'Test Election']);

        $this->artisan('evote:list:election')
            ->expectsTable(
                ['ID', 'Title', 'Description', 'Level', 'Owner', 'Abstainable', 'Deleted At', 'Created At', 'Updated At'],
                Election::all()->toArray()
            )
            ->assertExitCode(0);
    }
}
