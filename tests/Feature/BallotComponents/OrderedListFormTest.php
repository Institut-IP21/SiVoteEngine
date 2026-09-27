<?php

declare(strict_types=1);

namespace Tests\Feature\BallotComponents;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 13 (form render): the OrderedList voting form must reach the voter
 * through the REAL Livewire auto-discovery path — `@livewire(Str::kebab($type)
 * . '-livewire', ...)` in `ballot-component/form.blade.php` resolving
 * `ordered-list-livewire` to `App\Livewire\OrderedListLivewire` — not just via
 * a direct `Livewire::test(OrderedListLivewire::class, ...)` unit call. This
 * is the one test that would have caught a missing/wrong Livewire class-name
 * registration if Livewire 4's kebab-case auto-discovery had not applied.
 */
class OrderedListFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_ballot_page_renders_the_ordered_list_ranker_via_livewire_auto_discovery(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
            'finished' => false,
        ]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['Alpha', 'Bravo', 'Charlie'],
            'settings' => ['seats' => 2],
        ]);
        $vote = Vote::factory()->create(['ballot_id' => $ballot->id]);

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}?code={$vote->id}");

        $response->assertOk();
        // The shared ranker widget (reused verbatim from RankedChoice) rendered live,
        // not the static/preview fallback — proves ordered-list-livewire resolved via
        // Livewire 4's kebab-case auto-discovery (wire:name carries the component name).
        $response->assertSee('wire:name="ordered-list-livewire"', false);
        $response->assertSee('rc-interactive', false);
        $response->assertSee('rc-unranked', false);
        $response->assertSee('Alpha');
        $response->assertSee('Bravo');
        $response->assertSee('Charlie');
        $response->assertSee('livewire.js', false);
    }
}
