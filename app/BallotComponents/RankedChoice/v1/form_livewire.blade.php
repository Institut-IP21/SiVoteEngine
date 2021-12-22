<div>
    <p>
        {{ __('components.rankedchoice.intro', ['options' => count($component->options)]) }}
    </p>
    <hr class="py-2" />
    <p class="pb-2">
        {{ __('components.rankedchoice.state', ['remaining' => $rankees->count() - $selected->count(), 'selected' => $selected->count()]) }}
    </p>
    @if ($selected)
        @foreach ($selected as $option)
            <div class="row border border-blue-300 d-flex mb-1"
                wire:key="{{ count($selected) . $loop->index . $loop->first . $loop->last . $option['name'] . $option['rank'] }}">
                <div class="rank border-l border-r border-blue-300 rank text-2xl flex-1 py-2 px-4">
                    {{ $option['rank'] }}
                </div>
                <div class="border-r border-blue-300 flex-3 text-2xl py-2 px-4">
                    {{ $option['name'] }}
                </div>
                <div class="border-r border-blue-300 flex-1 text-2xl buttons d-flex">
                    <button type="button"
                        class="btn disabled:opacity-30 hover:bg-blue-100 hover:bg-opacity-25 disabled:bg-transparent"
                        wire:loading.attr="disabled" @if (count($selected) < 2 || $loop->first) disabled @endif wire:target="up, down, remove, select"
                        wire:click="up('{{ $option['name'] }}')">
                        <i>{{ __('components.rankedchoice.UP') }}</i>
                    </button>
                    <button type="button"
                        class="btn disabled:opacity-30 hover:bg-blue-100 hover:bg-opacity-25 disabled:bg-transparent"
                        wire:loading.attr="disabled" wire:click="down('{{ $option['name'] }}')"
                        wire:target="up, down, remove, select" @if (count($selected) < 2 || $loop->last) disabled @endif>
                        <i>{{ __('components.rankedchoice.DOWN') }}</i>
                    </button>
                    <button class="btn border-l bg-red-200 text-red-600 hover:text-white hover:bg-red-600 rounded-none"
                        wire:click.prevent="remove('{{ $option['name'] }}')">
                        <i>X</i>
                    </button>
                </div>
            </div>
        @endforeach
    @endif
    @if ($unselected)
        @foreach ($unselected as $option)
            <div class="row border border-gray-400 mb-1 d-flex cursor-pointer"
                wire:click="select('{{ $option['name'] }}', {{ $loop->index }})">
                <div class="hover:bg-blue-100 hover:bg-opacity-25 text-2xl flex-2 py-2 px-4">
                    {{ $option['name'] }}
                </div>
            </div>
        @endforeach
    @endif
    @if ($selected)
        @foreach ($selected as $option)
            <input type="hidden" v-for="rankee in selected" wire:key="{{ $option['name'] }}"
                name="{{ $component->id }}[]" value="{{ $option['name'] }}" />
        @endforeach
    @endif
</div>
