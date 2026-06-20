<?php

declare(strict_types=1);

namespace Tests\Feature\Owner;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * #10 — logo upload lands on the engine's OWN public disk and sets a same-origin
 * photo_url, so voter-facing pages never load a logo off a third-party host. SVG is
 * sanitized (scripts/handlers/remote refs stripped); raster is stored verbatim.
 */
class LogoUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $token = '123123123';
    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = fake()->uuid();
        Storage::fake('public');
    }

    private function headers(): array
    {
        return ['Authorization' => $this->token, 'Owner' => $this->owner];
    }

    private function expectedBase(): string
    {
        return 'logos/' . hash('sha256', $this->owner);
    }

    public function test_png_is_stored_and_photo_url_is_same_origin(): void
    {
        $res = $this->post('/api/owner/logo', [
            'logo' => UploadedFile::fake()->image('brand.png', 200, 80),
        ], $this->headers());

        $res->assertSuccessful();
        Storage::disk('public')->assertExists($this->expectedBase() . '.png');

        $photoUrl = $res->json('data.photo_url');
        $this->assertStringContainsString('/storage/logos/', (string) $photoUrl);
        // Never a remote/third-party host.
        $this->assertStringNotContainsString('http://evil', (string) $photoUrl);
        $this->assertDatabaseHas('personalizations', ['owner' => $this->owner]);
    }

    public function test_svg_is_sanitized_scripts_removed(): void
    {
        $dirtySvg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'
            . '<script>alert(1)</script>'
            . '<a xlink:href="http://evil.test/x"><rect width="10" height="10" onclick="steal()"/></a>'
            . '</svg>';

        $res = $this->post('/api/owner/logo', [
            'logo' => UploadedFile::fake()->createWithContent('brand.svg', $dirtySvg),
        ], $this->headers());

        $res->assertSuccessful();
        $path = $this->expectedBase() . '.svg';
        Storage::disk('public')->assertExists($path);

        $stored = Storage::disk('public')->get($path);
        $this->assertStringNotContainsStringIgnoringCase('<script', (string) $stored);
        $this->assertStringNotContainsStringIgnoringCase('onclick', (string) $stored);
        $this->assertStringNotContainsString('evil.test', (string) $stored);
        $this->assertStringContainsString('.svg', (string) $res->json('data.photo_url'));
    }

    public function test_oversized_file_is_rejected(): void
    {
        $res = $this->post('/api/owner/logo', [
            'logo' => UploadedFile::fake()->create('huge.png', 3000, 'image/png'), // 3 MB
        ], $this->headers());

        $res->assertStatus(400);
        Storage::disk('public')->assertDirectoryEmpty('logos');
    }

    public function test_unsupported_type_is_rejected(): void
    {
        $res = $this->post('/api/owner/logo', [
            'logo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ], $this->headers());

        $res->assertStatus(422);
        Storage::disk('public')->assertDirectoryEmpty('logos');
    }

    public function test_reupload_replaces_the_prior_logo(): void
    {
        $this->post('/api/owner/logo', [
            'logo' => UploadedFile::fake()->image('first.png'),
        ], $this->headers())->assertSuccessful();
        Storage::disk('public')->assertExists($this->expectedBase() . '.png');

        // Re-upload as SVG: the old PNG must be gone, only the SVG remains.
        $this->post('/api/owner/logo', [
            'logo' => UploadedFile::fake()->createWithContent('second.svg',
                '<svg xmlns="http://www.w3.org/2000/svg" width="4" height="4"><rect width="4" height="4"/></svg>'),
        ], $this->headers())->assertSuccessful();

        Storage::disk('public')->assertExists($this->expectedBase() . '.svg');
        Storage::disk('public')->assertMissing($this->expectedBase() . '.png');
    }

    public function test_unauthenticated_upload_is_rejected(): void
    {
        $this->post('/api/owner/logo', [
            'logo' => UploadedFile::fake()->image('x.png'),
        ])->assertUnauthorized();
    }
}
