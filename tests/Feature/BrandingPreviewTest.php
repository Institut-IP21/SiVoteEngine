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
        $res->assertSee('ballot-submit', false);
        $res->assertSee('Option A');
        $res->assertSee('opt-ctrl--radio', false);
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
