@props(['pers' => null])
@php
    // Customer accent override (structure stays ours; only the colour swaps). BrandPalette
    // re-validates the stored hex and owns the derived --color-brand* concepts; null means
    // "no valid override → default theme".
    $palette = \App\Support\BrandPalette::fromHex($pers?->brand_color);
@endphp
<div id="app" {{ $attributes->merge(['class' => 'min-h-screen bg-canvas']) }}
    @if ($palette) style="{{ $palette->cssVars() }}" @endif>
    <div class="max-w-screen-md mx-auto px-4 sm:px-6 py-6 sm:py-10">
        {{ $slot }}
    </div>
</div>
