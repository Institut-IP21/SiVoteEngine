<?php

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the Livewire 3 frontend wiring on the voter-facing ballot page.
 *
 * Specifically regression-guards the asset-URL bug fixed in 3723ed7: a
 * duplicate 'asset_url' key in config/livewire.php made Livewire emit a
 * script tag with no "/livewire/livewire.js" path, so Livewire + its bundled
 * Alpine never loaded. If asset_url is broken again, the path disappears
 * from the markup and this test fails.
 *
 * Note: this is a markup-level check. It cannot see the webpack-built
 * public/js/app.js, so it does NOT catch a standalone Alpine being bundled
 * back into app.js — that would need a browser/Dusk test.
 */
class BallotViewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_ballot_page_loads_livewire_assets_with_correct_path()
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
            'title' => 'Test Ballot Title',
        ]);
        $vote = Vote::factory()->create(['ballot_id' => $ballot->id]);

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}?code={$vote->id}");

        $response->assertOk();
        // Confirms we rendered the real ballot page, not the 404/expired view.
        $response->assertSee('Test Ballot Title');
        // Livewire assets injected with a working asset URL. Livewire 4 serves the
        // script from a hashed endpoint prefix (/livewire-<hash>/livewire.js) rather
        // than the L3 fixed "/livewire/" path, so assert the script file name, which
        // is stable across the prefix change and proves the asset URL is emitted.
        $response->assertSee('livewire.js', false);
    }
}
