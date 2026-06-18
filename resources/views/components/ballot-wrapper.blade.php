@props(['pers' => null])
@php
    // Customer accent override (structure stays ours; only the color swaps). Re-validate
    // the stored hex before emitting it into a style attribute — never trust it raw.
    $bc = $pers?->brand_color ?? null;
    $bc = is_string($bc) && preg_match('/^#[0-9a-fA-F]{6}$/', $bc) ? $bc : null;
@endphp
<div id="app" class="min-h-screen bg-canvas"
    @if ($bc) style="--color-brand: {{ $bc }}; --color-brand-dark: color-mix(in srgb, {{ $bc }} 82%, #000); --color-brand-soft: color-mix(in srgb, {{ $bc }} 10%, #fff); --color-brand-fg: #fff;" @endif>
    <div class="max-w-screen-md mx-auto px-4 sm:px-6 py-6 sm:py-10">
        {{ $slot }}
    </div>
</div>
