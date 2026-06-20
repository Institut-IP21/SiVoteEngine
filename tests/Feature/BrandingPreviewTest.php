<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Personalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #9 — the data-free branding preview the web_app branding page embeds: it renders
 * the REAL voter-facing shell with the owner's personalization + a synthetic sample,
 * and must work whether or not the owner has personalized yet.
 */
class BrandingPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_for_an_owner_without_personalization(): void
    {
        $res = $this->get('/branding-preview/' . fake()->uuid());

        $res->assertOk();
        // The real shell + a sample question render (no personalization row needed).
        // The default sample is a Yes/No motion (radio controls).
        $res->assertSee('ballot-submit', false);
        $res->assertSee(__('components.yesno.yes'));
        $res->assertSee('opt-ctrl--radio', false);
    }

    public function test_question_type_is_selectable_via_query(): void
    {
        $owner = fake()->uuid();

        // Choose-one shows the lettered options as radios.
        $fptp = $this->get('/branding-preview/' . $owner . '?type=FirstPastThePost');
        $fptp->assertOk();
        $fptp->assertSee('Option A');
        $fptp->assertSee('opt-ctrl--radio', false);

        // Approval shows checkbox controls.
        $approval = $this->get('/branding-preview/' . $owner . '?type=ApprovalVote');
        $approval->assertOk();
        $approval->assertSee('opt-ctrl--check', false);

        // An unknown type falls back to the Yes/No default rather than erroring.
        $bogus = $this->get('/branding-preview/' . $owner . '?type=Nonsense');
        $bogus->assertOk();
        $bogus->assertSee(__('components.yesno.yes'));
    }

    public function test_applies_the_saved_brand_color(): void
    {
        $owner = fake()->uuid();
        Personalization::create(['owner' => $owner, 'brand_color' => '#abcdef']);

        $res = $this->get('/branding-preview/' . $owner);

        $res->assertOk();
        // The wrapper emits the customer accent as an inline CSS variable.
        $res->assertSee('--color-brand: #abcdef', false);
    }

    public function test_is_framable_by_the_web_app_origin(): void
    {
        // frame.webapp middleware drops X-Frame-Options and pins frame-ancestors via CSP.
        $res = $this->get('/branding-preview/' . fake()->uuid());

        $res->assertOk();
        $res->assertHeaderMissing('X-Frame-Options');
        $this->assertStringContainsString('frame-ancestors', (string) $res->headers->get('Content-Security-Policy'));
    }
}
