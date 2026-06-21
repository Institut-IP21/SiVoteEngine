@php $total = count($component->options); @endphp
<div data-ranked-choice>
    <p class="text-[13px] text-muted leading-relaxed">{{ $component->type_hint }}</p>
    <p class="mt-2 mb-3.5 text-[11px] font-semibold text-muted">
        {{ __('components.rankedchoice.counter', ['selected' => $selected->count(), 'total' => $total]) }}
    </p>

    {{-- Ranked choice abstains implicitly (by ranking nothing). When the election allows
         abstaining and nothing is ranked yet, say so explicitly — other question types
         show an "abstain" option, this one can't. --}}
    @if ($abstainable && $selected->isEmpty())
        <div class="mb-3.5"><x-ballot-alert :icon="false">{{ __('components.rankedchoice.abstain_note') }}</x-ballot-alert></div>
    @endif

    {{-- Screen-reader announcement for every add / move / remove --}}
    <div class="sr-only" role="status" aria-live="polite">{{ $announce }}</div>

    {{-- Interactive widget (hidden when JS is unavailable; see <noscript> below) --}}
    <div class="rc-interactive">
        @if ($selected->isNotEmpty())
            <ul data-rc-sortable wire:key="ranked-list">
                @foreach ($selected as $option)
                    <li class="rc-ranked" data-name="{{ $option['name'] }}" wire:key="r-{{ md5($option['name']) }}">
                        <span class="rc-grip" aria-hidden="true" title="{{ __('components.rankedchoice.drag') }}">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h16M4 16h16" /></svg>
                        </span>
                        <button type="button" class="rc-badge"
                            wire:click="moveToTop(@js($option['name']))"
                            aria-label="{{ __('components.rankedchoice.move_top', ['name' => $option['name']]) }}">{{ $option['rank'] }}</button>
                        @include($component->component_path . '/_option_label', [
                            'name' => $option['name'],
                            'bold' => true,
                            'ariaLabel' => __('components.rankedchoice.position', ['name' => $option['name'], 'rank' => $option['rank'], 'total' => $selected->count()]),
                        ])
                        <span class="rc-break" aria-hidden="true"></span>
                        <span class="flex items-center gap-0.5 sm:ml-auto">
                            <button type="button" class="rc-ico" wire:click="up(@js($option['name']))" @disabled($loop->first)
                                aria-label="{{ __('components.rankedchoice.move_up', ['name' => $option['name']]) }}"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 15l7-7 7 7" /></svg></button>
                            <button type="button" class="rc-ico" wire:click="down(@js($option['name']))" @disabled($loop->last)
                                aria-label="{{ __('components.rankedchoice.move_down', ['name' => $option['name']]) }}"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 9l-7 7-7-7" /></svg></button>
                            <span class="w-px h-5 bg-line mx-1" aria-hidden="true"></span>
                            <button type="button" class="rc-ico rc-ico--rm" wire:click="remove(@js($option['name']))"
                                aria-label="{{ __('components.rankedchoice.remove', ['name' => $option['name']]) }}"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg></button>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($unselected->isNotEmpty())
            @if ($selected->isNotEmpty())
                <div class="my-3 border-t border-dashed border-line"></div>
                <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.rankedchoice.remaining') }}</p>
            @endif
            @foreach ($unselected as $option)
                @include($component->component_path . '/_unranked_option', ['name' => $option['name'], 'interactive' => true])
            @endforeach
        @endif

        {{-- Submission: ordered list of option names, name="{id}[]" — unchanged contract --}}
        @foreach ($selected as $option)
            <input type="hidden" name="{{ $component->id }}[]" value="{{ $option['name'] }}" wire:key="h-{{ md5($option['name']) }}">
        @endforeach
    </div>

    {{-- RankedChoice is the one inherently-interactive question type. Without JS the
         Livewire widget can't run, so we show a notice rather than a broken control. --}}
    <noscript>
        <style>.rc-interactive{display:none !important;}</style>
        <x-ballot-alert :icon="false">{{ __('components.rankedchoice.requires_js') }}</x-ballot-alert>
    </noscript>
</div>
