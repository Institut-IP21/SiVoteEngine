<?php

declare(strict_types=1);

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The top-right type label doubles as the trigger for an info modal giving the
 * lay ("how it works") explanation of that voting method — on BOTH the
 * voter-facing ballot and the public results page, since both render the
 * shared `x-ballot-component.title` component.
 */
class BallotComponentInfoModalTest extends TestCase
{
    use RefreshDatabase;

    private function ballotWith(string $type, array $options): Ballot
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => $type,
            'version' => 'v1',
            'options' => $options,
        ]);

        return $ballot;
    }

    public function test_ballot_preview_shows_an_accessible_info_trigger_and_lay_explanation(): void
    {
        $ballot = $this->ballotWith('ApprovalVote', ['X', 'Y', 'Z']);

        $response = $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/preview");

        $response->assertOk();
        // A real <button> carrying aria-haspopup="dialog", not a plain span.
        $response->assertSee('aria-haspopup="dialog"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        // The lay explanation itself is present in the markup (inside the
        // x-cloak'd modal, which is a CSS-level hide, not absent from the HTML).
        $response->assertSee(__('components.approval.lay_explanation'));
    }

    public function test_result_page_shows_the_same_info_trigger_and_lay_explanation(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id, 'finished' => true]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => ['Ana', 'Betty'],
            'active' => true,
        ]);
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => [$ballot->components[0]->id => 'Ana'],
        ]);

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertOk();
        $response->assertSee('aria-haspopup="dialog"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee(__('components.fptp.lay_explanation'));
    }

    public function test_info_modal_never_uses_a_colored_left_border_accent_rail(): void
    {
        // Owner standing UI rule: no accent left-border rail on the modal/card.
        $ballot = $this->ballotWith('YesNo', ['yes', 'no']);

        $response = $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/preview");

        $response->assertOk();
        $response->assertDontSee('border-left', false);
    }
}
