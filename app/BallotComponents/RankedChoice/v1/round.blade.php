<div class="round px-1 pt-2 flex">
    @if (array_key_exists('_state', $round))
        <div class="round_state flex flex-col justify-center w-full">
            <b class="border bg-gray-200 row px-8 py-2">{{ __('components.rankedchoice.round') }}
                {{ $round_prefix . ($i + 1) }}</b>
            @foreach ($round['_state'] as $name => $votes)
                <div class="border-t row px-2 py-1
                    {{ $name === 'winner' ? 'bg-green-200' : '' }}
                    {{ !in_array($name, ['winner', 'eliminated']) && min($round['_state']) === $votes ? 'bg-yellow-200' : '' }}
                    {{ $name === 'eliminated' ? 'bg-red-200' : '' }}">
                    <span class=" flex-1">
                        @if ($name === 'winner')
                            {{ __('components.winner') }}
                        @elseif ($name === 'eliminated')
                            {{ __('components.eliminated') }}
                        @else
                            {{ $name }}
                        @endif
                    </span>
                    <span class="flex-1 text-right">{{ $votes === 'tie' ? __('components.tie') : $votes }} </span>
                </div>
            @endforeach
        </div>
        <div class="sub_rounds flex flex-col">
            @foreach ($round['splitElimination'] as $choice => $rounds)
                <div class="sub_round border flex">
                    <b class="bg-yellow-200 tie-header row px-4 py-2 m-1">{{ __('components.rankedchoice.tie_elimination') }}
                        {{ $choice }}</b>
                    @foreach ($rounds as $j => $sub_round)
                        @include($component->component_path . '/round', ['round' => $sub_round, 'component' =>
                        $component ,
                        'i'
                        => $loop->parent->index,
                        'round_prefix' => $round_prefix . ($i+$j+2) . '.'])
                    @endforeach
                </div>
            @endforeach
        </div>
    @else
        <div class="round_state flex flex-col justify-center w-full">
            <b class="border bg-gray-200 row px-8 py-2">{{ __('components.rankedchoice.round') }}
                {{ $round_prefix . ($i + 1) }}</b>
            @foreach ($round as $name => $votes)
                @if ($name !== 'eliminated_previously')
                    <div class="border-t row px-2 py-1
                            {{ $name === 'winner' ? 'bg-green-200' : '' }}
                            {{ !array_diff(array_keys($round), ['winner', 'eliminated']) && min($round) === $votes ? 'bg-yellow-200' : '' }}
                            {{ $name === 'eliminated' ? 'bg-red-200' : '' }}">
                        <span class="flex-1">
                            @if ($name === 'winner')
                                {{ __('components.winner') }}
                            @elseif ($name === 'eliminated')
                                {{ __('components.eliminated') }}
                            @else
                                {{ $name }}
                            @endif
                        </span>
                        <span class="flex-1 text-right">
                            {{ $votes === 'tie' ? __('components.tie') : $votes }}
                        </span>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
