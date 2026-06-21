<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BrandPalette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BrandPaletteTest extends TestCase
{
    public function test_from_hex_rejects_absent_or_malformed_values(): void
    {
        $this->assertNull(BrandPalette::fromHex(null));
        $this->assertNull(BrandPalette::fromHex(''));
        $this->assertNull(BrandPalette::fromHex('not-a-hex'));
        $this->assertNull(BrandPalette::fromHex('#abc'));       // 3-digit not supported
        $this->assertNull(BrandPalette::fromHex('#12345g'));
        $this->assertInstanceOf(BrandPalette::class, BrandPalette::fromHex('#34b6df'));
    }

    #[DataProvider('foregroundCases')]
    public function test_foreground_picks_a_readable_text_colour(string $bg, string $expected): void
    {
        $this->assertSame($expected, BrandPalette::fromHex($bg)->foreground());
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function foregroundCases(): array
    {
        return [
            'white -> dark'         => ['#ffffff', '#11161a'],
            'black -> white'        => ['#000000', '#ffffff'],
            'bright yellow -> dark' => ['#ffeb3b', '#11161a'],
            'navy -> white'         => ['#1a237e', '#ffffff'],
            'mid cyan-blue -> dark' => ['#34b6df', '#11161a'],
        ];
    }

    public function test_css_vars_emit_the_brand_concepts(): void
    {
        $vars = BrandPalette::fromHex('#34b6df')->cssVars();

        $this->assertStringContainsString('--color-brand: #34b6df;', $vars);
        $this->assertStringContainsString('--color-brand-dark: color-mix(in srgb, #34b6df 82%, #000);', $vars);
        $this->assertStringContainsString('--color-brand-soft: color-mix(in srgb, #34b6df 10%, #fff);', $vars);
        $this->assertStringContainsString('--color-brand-fg: #11161a;', $vars);
    }
}
