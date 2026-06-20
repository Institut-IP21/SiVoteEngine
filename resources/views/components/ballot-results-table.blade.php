@props(['shareLabel' => null])
<div class="flex flex-row text-[11px] uppercase tracking-[.05em] font-bold text-muted">
    <div class="flex-1 border border-line p-3">{{ __('components.option') }}</div>
    <div class="flex-1 border border-line p-3">{{ __('components.votes') }}</div>
    <div class="flex-1 border border-line p-3">{{ $shareLabel ?? __('components.oftotal') }}</div>
</div>
{{ $slot }}