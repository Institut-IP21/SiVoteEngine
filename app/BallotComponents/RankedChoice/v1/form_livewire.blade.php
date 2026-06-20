@php $total = count($component->options); @endphp
<div data-ranked-choice>
    <p class="text-[13px] text-muted leading-relaxed">{{ $component->type_hint }}</p>
    <p class="mt-2 mb-3.5 text-[11px] font-semibold text-muted">
        {{ __('components.rankedchoice.counter', ['selected' => $selected->count(), 'total' => $total]) }}
    </p>

    {{-- Screen-reader announcement for every add / move / remove --}}
    <div class="sr-only" role="status" aria-live="polite">{{ $announce }}</div>

    {{-- Interactive widget (hidden when JS is unavailable; see <noscript> below) --}}
    <div class="rc-interactive">
        @if ($selected->isNotEmpty())
            <ul data-rc-sortable wire:key="ranked-list">
                @foreach ($selected as $option)
                    <li class="rc-ranked" data-name="{{ $option['name'] }}" wire:key="r-{{ md5($option['name']) }}">
                        <span class="rc-grip" aria-hidden="true" title="{{ __('components.rankedchoice.drag') }}">⠿</span>
                        <button type="button" class="rc-badge"
                            wire:click="moveToTop(@js($option['name']))"
                            aria-label="{{ __('components.rankedchoice.move_top', ['name' => $option['name']]) }}">{{ $option['rank'] }}</button>
                        <span class="flex-1 min-w-0 text-[15px] font-semibold text-brand-fg leading-snug"
                            aria-label="{{ __('components.rankedchoice.position', ['name' => $option['name'], 'rank' => $option['rank'], 'total' => $selected->count()]) }}">{{ $option['name'] }}</span>
                        <span class="rc-break" aria-hidden="true"></span>
                        <span class="flex items-center gap-0.5 sm:ml-auto">
                            <button type="button" class="rc-ico" wire:click="up(@js($option['name']))" @disabled($loop->first)
                                aria-label="{{ __('components.rankedchoice.move_up', ['name' => $option['name']]) }}">↑</button>
                            <button type="button" class="rc-ico" wire:click="down(@js($option['name']))" @disabled($loop->last)
                                aria-label="{{ __('components.rankedchoice.move_down', ['name' => $option['name']]) }}">↓</button>
                            <span class="w-px h-5 bg-line mx-1" aria-hidden="true"></span>
                            <button type="button" class="rc-ico rc-ico--rm" wire:click="remove(@js($option['name']))"
                                aria-label="{{ __('components.rankedchoice.remove', ['name' => $option['name']]) }}">✕</button>
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
                <button type="button" class="rc-unranked" wire:click="select(@js($option['name']))" wire:key="u-{{ md5($option['name']) }}"
                    aria-label="{{ __('components.rankedchoice.add', ['name' => $option['name']]) }}">
                    <span class="rc-badge-empty" aria-hidden="true"></span>
                    <span class="flex-1 min-w-0 text-[15px] text-ink leading-snug">{{ $option['name'] }}</span>
                    <span class="text-[13px] font-bold text-brand-dark flex-shrink-0">{{ __('components.rankedchoice.add_short') }}</span>
                </button>
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
