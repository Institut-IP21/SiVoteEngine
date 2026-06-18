<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use App\Models\Personalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The optional per-owner ballot accent color added to the personalization endpoint.
 * It is rendered into an inline style on voter ballots, so validation must be strict
 * (#rrggbb only) and the field must stay optional (logo-only updates still work).
 */
class PersonalizationBrandColorTest extends TestCase
{
    use RefreshDatabase;

    private string $token = '123123123';
    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = fake()->uuid();
    }

    private function headers(): array
    {
        return ['Authorization' => $this->token, 'Owner' => $this->owner];
    }

    private function submit(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/owner/personalization', $payload, $this->headers());
    }

    public function test_a_valid_hex_brand_color_is_stored_and_returned(): void
    {
        $res = $this->submit([
            'photo_url' => 'https://example.test/logo.png',
            'brand_color' => '#34B6DF',
        ]);

        $res->assertSuccessful();
        $res->assertJsonPath('data.brand_color', '#34B6DF');
        $this->assertDatabaseHas('personalizations', [
            'owner' => $this->owner,
            'brand_color' => '#34B6DF',
        ]);
    }

    public function test_brand_color_is_optional_logo_only_update_still_works(): void
    {
        $res = $this->submit(['photo_url' => 'https://example.test/logo.png']);

        $res->assertSuccessful();
        $this->assertDatabaseHas('personalizations', [
            'owner' => $this->owner,
            'brand_color' => null,
        ]);
    }

    #[DataProvider('invalidColors')]
    public function test_an_invalid_brand_color_is_rejected(string $bad): void
    {
        $res = $this->submit([
            'photo_url' => 'https://example.test/logo.png',
            'brand_color' => $bad,
        ]);

        $res->assertStatus(400);
        $this->assertDatabaseMissing('personalizations', ['owner' => $this->owner]);
    }

    /** @return array<string, array{string}> */
    public static function invalidColors(): array
    {
        return [
            'word' => ['red'],
            'three-digit' => ['#fff'],
            'non-hex' => ['#xyzxyz'],
            'missing hash' => ['34b6df'],
            'injection' => ['#fff;}body{display:none'],
            'js scheme' => ['javascript:alert(1)'],
        ];
    }

    public function test_logo_only_update_preserves_an_existing_brand_color(): void
    {
        // The current web_app flow sends only photo_url; it must NOT wipe a set color.
        Personalization::create([
            'owner' => $this->owner,
            'photo_url' => 'https://example.test/old.png',
            'brand_color' => '#abcabc',
        ]);

        $this->submit(['photo_url' => 'https://example.test/new.png'])->assertSuccessful();

        $this->assertDatabaseHas('personalizations', [
            'owner' => $this->owner,
            'photo_url' => 'https://example.test/new.png',
            'brand_color' => '#abcabc',
        ]);
    }

    public function test_updating_color_does_not_wipe_an_existing_logo(): void
    {
        Personalization::create([
            'owner' => $this->owner,
            'photo_url' => 'https://example.test/logo.png',
        ]);

        $this->submit([
            'photo_url' => 'https://example.test/logo.png',
            'brand_color' => '#102030',
        ])->assertSuccessful();

        $this->assertDatabaseHas('personalizations', [
            'owner' => $this->owner,
            'photo_url' => 'https://example.test/logo.png',
            'brand_color' => '#102030',
        ]);
    }
}
