<?php

declare(strict_types=1);

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Personalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the redesigned voter-facing ballot markup: the brand option rows for each
 * component type, the per-element type label (sourced from the component tree, not a
 * hardcoded map), the RankedChoice widget + its no-JS fallback, the customer-color
 * override, and the removal of the external font CDN. Uses the (unauthenticated)
 * preview route, which renders every component regardless of ballot state.
 */
class BallotRedesignRenderTest extends TestCase
{
    use RefreshDatabase;

    private function ballotWith(string $type, array $options, ?string $owner = null): Ballot
    {
        $election = Election::factory()->create($owner ? ['owner' => $owner] : []);
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => $type,
            'version' => 'v1',
            'options' => $options,
        ]);

        return $ballot;
    }

    private function preview(Ballot $ballot): \Illuminate\Testing\TestResponse
    {
        return $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/preview");
    }

    public function test_yesno_and_fptp_render_brand_radio_rows(): void
    {
        foreach (['YesNo' => ['yes', 'no'], 'FirstPastThePost' => ['Ana', 'Bojan']] as $type => $opts) {
            $res = $this->preview($this->ballotWith($type, $opts));
            $res->assertOk();
            $res->assertSee('opt-row', false);
            $res->assertSee('opt-ctrl--radio', false);
            $res->assertSee('type="radio"', false);
        }
    }

    public function test_approval_renders_brand_checkbox_rows(): void
    {
        $res = $this->preview($this->ballotWith('ApprovalVote', ['X', 'Y', 'Z']));
        $res->assertOk();
        $res->assertSee('opt-ctrl--check', false);
        $res->assertSee('type="checkbox"', false);
    }

    public function test_ranked_choice_renders_widget_marker_and_options(): void
    {
        $res = $this->preview($this->ballotWith('RankedChoice', ['Lj', 'Mb', 'Online']));
        $res->assertOk();
        // The stable marker that gates the (code-split) drag enhancement.
        $res->assertSee('data-ranked-choice', false);
        // Nothing ranked yet -> options appear as unranked rows.
        $res->assertSee('rc-unranked', false);
        $res->assertSee('Online');
    }

    public function test_ranked_choice_has_a_no_js_fallback_notice(): void
    {
        $res = $this->preview($this->ballotWith('RankedChoice', ['A', 'B']));
        $res->assertSee('<noscript>', false);
        $res->assertSee(__('components.rankedchoice.requires_js'));
    }

    public function test_each_element_shows_its_type_label_from_the_component_tree(): void
    {
        $res = $this->preview($this->ballotWith('RankedChoice', ['A', 'B']));
        // The localized type name comes from the component's getStrings()['name'].
        $res->assertSee(__('components.rankedchoice.name'));
    }

    public function test_customer_brand_color_is_emitted_as_an_inline_override_when_set(): void
    {
        $ballot = $this->ballotWith('YesNo', ['yes', 'no'], owner: (string) fake()->uuid());
        Personalization::create([
            'owner' => $ballot->election->owner,
            'photo_url' => 'https://example.test/logo.png',
            'brand_color' => '#7b2d8e',
        ]);

        $res = $this->preview($ballot);
        $res->assertSee('--color-brand: #7b2d8e', false);
    }

    public function test_no_brand_color_means_no_inline_override(): void
    {
        $res = $this->preview($this->ballotWith('YesNo', ['yes', 'no']));
        $res->assertOk();
        $res->assertDontSee('--color-brand:', false);
    }

    public function test_an_invalid_stored_brand_color_is_never_emitted(): void
    {
        // Defence in depth: even if a bad value reaches the column, the view's hex
        // guard must refuse to echo it into the style attribute.
        $ballot = $this->ballotWith('YesNo', ['yes', 'no'], owner: (string) fake()->uuid());
        Personalization::create([
            'owner' => $ballot->election->owner,
            'photo_url' => 'https://example.test/logo.png',
            'brand_color' => 'red;}body{display:none',
        ]);

        $res = $this->preview($ballot);
        $res->assertOk();
        $res->assertDontSee('red;}body{display:none', false);
        $res->assertDontSee('--color-brand:', false);
    }

    public function test_ballot_does_not_load_an_external_font_cdn(): void
    {
        $res = $this->preview($this->ballotWith('YesNo', ['yes', 'no']));
        $res->assertDontSee('fonts.googleapis.com');
        $res->assertDontSee('fonts.gstatic.com');
    }

    public function test_no_logo_chip_when_owner_has_no_personalization(): void
    {
        // ballot-logo renders nothing without a photo_url (no empty white chip).
        $res = $this->preview($this->ballotWith('YesNo', ['yes', 'no']));
        $res->assertDontSee('<img', false);
    }
}
