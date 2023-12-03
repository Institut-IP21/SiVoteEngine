<div class="pb-4">
    <p class="text-sm sm:text-base">
        {{ __('components.rankedchoice.intro', ['options' => count($component->options)]) }}
    </p>
    <p class="py-6 text-center w-full text-sm sm:text-base">
        {{ __('components.rankedchoice.state', ['remaining' => $rankees->count() - $selected->count(), 'selected' => $selected->count()]) }}
    </p>
    @if ($selected)
    @foreach ($selected as $option)
    <div class="row border border-blue-300 flex flex-col sm:flex-row items-stretch sm:items-center mb-4"
        wire:key="{{ count($selected) . $loop->index . $loop->first . $loop->last . $option['name'] . $option['rank'] }}">
        <div class="flex items-center flex-1">
            <div class="rank border-blue-300 rank text-2xl flex-1 py-2 pl-4 pr-6 w-10 sm:w-16 flex-grow-0">
                {{ $option['rank'] }}
            </div>
            <div class="question sm:border-r border-blue-300 flex-3 py-3 px-4 flex-shrink-0 min-w-1/2">
                {{ $option['name'] }}
            </div>
        </div>
        <div class="border-t sm:border-t-0 border-blue-300 text-2xl buttons flex">
            <button type="button"
                class="btn disabled:opacity-30 hover:bg-blue-100 hover:bg-opacity-25 disabled:bg-transparent w-2/5"
                wire:loading.attr="disabled" @if (count($selected) < 2 || $loop->first) disabled @endif wire:target="up,
                down, remove, select"
                wire:click="up('{{ $option['name'] }}')">
                <i>{{ __('components.rankedchoice.UP') }}</i>
            </button>
            <button type="button"
                class="btn disabled:opacity-30 hover:bg-blue-100 hover:bg-opacity-25 disabled:bg-transparent w-2/5"
                wire:loading.attr="disabled" wire:click="down('{{ $option['name'] }}')"
                wire:target="up, down, remove, select" @if (count($selected) < 2 || $loop->last) disabled @endif>
                <i>{{ __('components.rankedchoice.DOWN') }}</i>
            </button>
            <button
                class="btn border-l bg-red-200 text-red-600 hover:text-white hover:bg-red-600 rounded-none w-1/5 flex justify-center items-center"
                wire:click.prevent="remove('{{ $option['name'] }}')">
                <i>X</i>
            </button>
        </div>
    </div>
    @endforeach
    @endif
    @if ($unselected)
    @foreach ($unselected as $option)
    <div class="row border border-gray-400 mb-4 flex cursor-pointer"
        wire:click="select('{{ $option['name'] }}', {{ $loop->index }})">
        <div class="question hover:bg-blue-100 hover:bg-opacity-25 flex-2 py-2 px-4">
            {{ $option['name'] }}
        </div>
    </div>
    @endforeach
    @endif
    @if ($selected)
    @foreach ($selected as $option)
    <input type="hidden" v-for="rankee in selected" wire:key="{{ $option['name'] }}" name="{{ $component->id }}[]"
        value="{{ $option['name'] }}" />
    @endforeach
    @endif
</div>
