@php $options = is_array($component->options) ? array_values($component->options) : []; @endphp
{{-- Static, non-interactive ranked-choice for the embedded builder preview. Same look
     as the live widget's initial (nothing-ranked-yet) state, but no Livewire and no
     server round-trips — so it can't throw the cross-origin "page expired" (419). The
     real ballot and the standalone full-page preview use the interactive widget. --}}
<div data-ranked-choice-preview>
    <p class="text-[13px] text-muted leading-relaxed">{{ __('components.rankedchoice.hint') }}</p>
    <p class="mt-2 mb-3.5 text-[11px] font-semibold text-muted">
        {{ __('components.rankedchoice.counter', ['selected' => 0, 'total' => count($options)]) }}
    </p>

    <div class="rc-interactive" style="pointer-events:none" aria-hidden="true">
        @foreach ($options as $option)
            <div class="rc-unranked" data-rc-preview-option>
                <span class="rc-badge-empty" aria-hidden="true"></span>
                <span class="flex-1 min-w-0 text-[15px] text-ink leading-snug" style="min-width:0;overflow-wrap:anywhere">{{ $option }}</span>
                <span class="text-[13px] font-bold text-brand-dark flex-shrink-0">{{ __('components.rankedchoice.add_short') }}</span>
            </div>
        @endforeach
    </div>

    <p class="mt-3 text-[11px] text-muted italic">{{ __('components.rankedchoice.preview_static') }}</p>
</div>
