{{-- Quorum-fail per-question line. The top-level quorum panel already flags the failed
     quorum in red; this calm muted line stands in for the suppressed verdict so each
     question still reads on its own (a result with no stated outcome looks unfinished).
     Pass a slot to override the default copy with a richer, type-specific sentence. --}}
<div {{ $attributes->merge(['class' => 'mb-4 px-4 py-3 rounded-xl border border-line bg-canvas text-center text-[13px] text-muted']) }}>
    {{ $slot->isEmpty() ? __('components.not_binding') : $slot }}
</div>
