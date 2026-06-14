<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\RankedChoiceLivewire;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The ranked-choice ranking widget (basic-mode voting UI). It mutates only
 * client-side ranking state; the IRV tally lives in RankedChoice.php and is
 * tested separately. Here we pin the select / up / down / remove / render
 * transitions a voter drives.
 */
class RankedChoiceLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function makeWidget(): \Livewire\Features\SupportTesting\Testable
    {
        $ballot = Ballot::factory()->create(['election_id' => Election::factory()->create()->id]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'RankedChoice',
            'version' => 'v1',
            'options' => ['A', 'B', 'C'],
        ]);

        return Livewire::test(RankedChoiceLivewire::class, [
            'ballot' => $ballot,
            'component' => $component,
        ]);
    }

    /**
     * Rank of a named rankee in the current component state, or null.
     */
    private function rankOf(\Livewire\Features\SupportTesting\Testable $t, string $name): ?int
    {
        $rankee = collect($t->get('rankees'))->firstWhere('name', $name);

        return $rankee['rank'] === null ? null : (int) $rankee['rank'];
    }

    public function test_mount_seeds_every_option_unranked(): void
    {
        $t = $this->makeWidget();

        $rankees = collect($t->get('rankees'));
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $rankees->pluck('name')->all());
        $this->assertTrue($rankees->every(fn ($r): bool => $r['rank'] === null));
    }

    public function test_select_assigns_the_next_rank_in_order(): void
    {
        $t = $this->makeWidget();

        $t->call('select', 'A');
        $this->assertSame(1, $this->rankOf($t, 'A'));

        $t->call('select', 'C');
        $this->assertSame(2, $this->rankOf($t, 'C'));

        // Unselected option stays unranked.
        $this->assertNull($this->rankOf($t, 'B'));
    }

    public function test_up_swaps_a_rankee_with_the_one_above_it(): void
    {
        $t = $this->makeWidget();
        $t->call('select', 'A')->call('select', 'B')->call('select', 'C'); // A=1,B=2,C=3

        $t->call('up', 'C'); // C climbs above B

        $this->assertSame(1, $this->rankOf($t, 'A'));
        $this->assertSame(2, $this->rankOf($t, 'C'));
        $this->assertSame(3, $this->rankOf($t, 'B'));
    }

    public function test_down_swaps_a_rankee_with_the_one_below_it(): void
    {
        $t = $this->makeWidget();
        $t->call('select', 'A')->call('select', 'B')->call('select', 'C'); // A=1,B=2,C=3

        $t->call('down', 'A'); // A sinks below B

        $this->assertSame(1, $this->rankOf($t, 'B'));
        $this->assertSame(2, $this->rankOf($t, 'A'));
        $this->assertSame(3, $this->rankOf($t, 'C'));
    }

    public function test_remove_unranks_and_compacts_the_remaining_ranks(): void
    {
        $t = $this->makeWidget();
        $t->call('select', 'A')->call('select', 'B')->call('select', 'C'); // A=1,B=2,C=3

        $t->call('remove', 'B'); // gap at 2 must close

        $this->assertSame(1, $this->rankOf($t, 'A'));
        $this->assertNull($this->rankOf($t, 'B'));
        $this->assertSame(2, $this->rankOf($t, 'C'));
    }

    public function test_reselecting_after_removal_takes_a_fresh_top_rank(): void
    {
        $t = $this->makeWidget();
        $t->call('select', 'A')->call('select', 'B')->call('select', 'C');
        $t->call('remove', 'B'); // A=1, C=2

        $t->call('select', 'B'); // max(1,2)+1

        $this->assertSame(3, $this->rankOf($t, 'B'));
    }

    public function test_render_partitions_selected_sorted_from_unselected(): void
    {
        $t = $this->makeWidget();
        $t->call('select', 'C')->call('select', 'A'); // C=1, A=2

        $selected = collect($t->get('selected'));
        $unselected = collect($t->get('unselected'));

        // selected is sorted by rank, so C (rank 1) precedes A (rank 2).
        $this->assertSame(['C', 'A'], $selected->pluck('name')->all());
        $this->assertSame(['B'], $unselected->pluck('name')->values()->all());
    }
}
