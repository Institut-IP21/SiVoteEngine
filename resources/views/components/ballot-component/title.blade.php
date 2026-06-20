@props(['component'])

{{-- The type label (top-right of every ballot element) is the component's localized
     name, resolved from the registry via $component->type_name — the single source of
     truth (each component's getStrings()['name']). --}}
<div class="flex items-baseline justify-between gap-3">
    <h2 class="font-bold text-base sm:text-lg text-ink leading-snug" style="min-width:0">{{ $component->title }}</h2>
    @if ($component->type_name)
        {{-- Let the type label wrap instead of overflowing the ballot on narrow (phone)
             widths: drop flex-shrink-0 (inline styles, so no new utility classes to
             compile) and allow the words to break. --}}
        <span class="text-[11px] font-semibold uppercase tracking-[0.05em] text-muted" style="overflow-wrap:anywhere;text-align:right">{{ $component->type_name }}</span>
    @endif
</div>
