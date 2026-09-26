{{-- Shared unranked ranked-choice option row: empty rank badge · label · "Add" hint.
     Rendered as an interactive <button> on the live ballot and as a static <div> in
     the cross-origin builder preview (which can't run the Livewire widget), so the
     two presentations are guaranteed identical. Params: $name (string);
     $interactive (bool, default false). $component comes from the including view. --}}
@php($interactive = $interactive ?? false)
@if ($interactive)
    <button type="button" class="rc-unranked" wire:click="select(@js($name))" wire:key="u-{{ md5($name) }}"
        aria-label="{{ __('components.rankedchoice.add', ['name' => $name]) }}">
        <span class="rc-badge-empty" aria-hidden="true"></span>
        @include($component->component_path . '/_option_label', ['name' => $name])
        <span class="text-[13px] font-bold text-brand-dark flex-shrink-0">{{ __('components.rankedchoice.add_short') }}</span>
    </button>
@else
    <div class="rc-unranked" data-rc-preview-option>
        <span class="rc-badge-empty" aria-hidden="true"></span>
        @include($component->component_path . '/_option_label', ['name' => $name])
        <span class="text-[13px] font-bold text-brand-dark flex-shrink-0">{{ __('components.rankedchoice.add_short') }}</span>
    </div>
@endif
