@props(['icon' => true])
{{-- Shared warning/alert box (tokenized: warn-line border, warn-soft bg, warn-fg text). --}}
<div {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-xl border border-warn-line bg-warn-soft px-4 py-3 text-sm text-warn-fg']) }}>
    @if ($icon)
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.42 0Z" />
        </svg>
    @endif
    <span>{{ $slot }}</span>
</div>
