<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Picks a readable text colour for a given brand/accent background. A bright accent
 * (e.g. yellow) needs dark text; a dark accent (e.g. navy) needs white. Uses the WCAG
 * relative-luminance contrast ratio and returns whichever of white / near-black
 * contrasts better against the background.
 */
final class BrandContrast
{
    /** Near-black ink used when white text wouldn't contrast on a light accent. */
    private const DARK = '#11161a';
    private const LIGHT = '#ffffff';

    public static function foreground(?string $hex): string
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) {
            return self::LIGHT;
        }

        $bg = self::luminance($hex);
        $contrastWithWhite = (1.0 + 0.05) / ($bg + 0.05);
        $contrastWithDark = ($bg + 0.05) / (self::luminance(self::DARK) + 0.05);

        return $contrastWithDark > $contrastWithWhite ? self::DARK : self::LIGHT;
    }

    /** WCAG relative luminance of a #rrggbb colour. */
    private static function luminance(string $hex): float
    {
        $channels = [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];
        $linear = array_map(
            static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $channels
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
