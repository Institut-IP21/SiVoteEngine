<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The single owner of "the org brand palette" for the engine's voter-facing pages.
 * Given a stored accent hex it exposes the derived colour concepts the UI consumes
 * (the `--color-brand*` CSS variables) and the WCAG-aware foreground choice, so the
 * ballot wrapper never re-derives them inline. fromHex() returns null for an absent /
 * malformed value — the "no brand override, use the default theme" signal.
 *
 * (Deliberately mirrored — not shared — across the three apps, like the wire DTOs:
 * web_sender/web_app carry their own slimmer copy of the same core.)
 */
final class BrandPalette
{
    /** Near-black ink used when white text wouldn't contrast on a light accent. */
    private const DARK_INK = '#11161a';
    private const WHITE = '#ffffff';

    private function __construct(public readonly string $color) {}

    public static function fromHex(?string $hex): ?self
    {
        if (! is_string($hex) || ! preg_match('/^#[0-9a-fA-F]{6}$/D', $hex)) {
            return null;
        }

        return new self($hex);
    }

    /** Readable text colour on the accent: near-black on light accents, white on dark. */
    public function foreground(): string
    {
        return $this->contrast(self::DARK_INK) > $this->contrast(self::WHITE)
            ? self::DARK_INK
            : self::WHITE;
    }

    /** WCAG contrast ratio between the accent and another #rrggbb colour. */
    public function contrast(string $other): float
    {
        $a = self::luminance($this->color);
        $b = self::luminance($other);
        [$hi, $lo] = $a > $b ? [$a, $b] : [$b, $a];

        return ($hi + 0.05) / ($lo + 0.05);
    }

    /**
     * The `--color-brand*` custom-property block for a `style` attribute. dark/soft are
     * left as CSS color-mix() (browser-computed, as before); fg is the adaptive choice.
     */
    public function cssVars(): string
    {
        return sprintf(
            '--color-brand: %1$s; --color-brand-dark: color-mix(in srgb, %1$s 82%%, #000); --color-brand-soft: color-mix(in srgb, %1$s 10%%, #fff); --color-brand-fg: %2$s;',
            $this->color,
            $this->foreground(),
        );
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
