@props(['component', 'typeName' => null])

{{-- $typeName is the component's localized type label, resolved by the caller from the
     component tree (each component's getStrings()['name']) — the single source of truth.
     Shown top-right of every ballot element. --}}
<div class="flex items-baseline justify-between gap-3">
    <h2 class="font-bold text-base sm:text-lg text-ink leading-snug">{{ $component->title }}</h2>
    @if ($typeName)
        <span class="flex-shrink-0 text-[11px] font-semibold uppercase tracking-[0.05em] text-muted">{{ $typeName }}</span>
    @endif
</div>
