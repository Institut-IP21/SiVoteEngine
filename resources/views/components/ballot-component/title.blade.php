@props(['component'])

{{-- The type label (top-right of every ballot element) is the component's localized
     name, resolved from the registry via $component->type_name — the single source of
     truth (each component's getStrings()['name']). --}}
<div class="flex items-baseline justify-between gap-3">
    <h2 class="font-bold text-base sm:text-lg text-ink leading-snug">{{ $component->title }}</h2>
    @if ($component->type_name)
        <span class="flex-shrink-0 text-[11px] font-semibold uppercase tracking-[0.05em] text-muted">{{ $component->type_name }}</span>
    @endif
</div>
