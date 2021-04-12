<div class="px-1 pt-2 flex justify-center flex-col items-center">
    @if (array_key_exists('_state', $round))
        <div class="round_state w-full">
            <b class="border bg-gray-200 row px-8 py-2">Round {{ $round_prefix . ($i + 1) }}</b>
            @foreach ($round['_state'] as $name => $options)
                <div
                    class="border-t row px-2 py-1 {{ $name === 'eliminated' ? 'bg-red-200' : '' }} {{ $name === 'winner' ? 'bg-green-200' : '' }}">
                    <span class="flex-1">
                        {{ $name }}
                    </span>
                    <span class="flex-1 text-right">{{ $options }} </span>
                </div>
            @endforeach
        </div>
        <div class="sub_rounds flex">
            @foreach ($round['splitElimination'] as $choice => $rounds)
                <div class="sub_round border">
                    <b class="bg-yellow-200 row px-4 py-2 m-1">Eliminating {{ $choice }}</b>
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
        <div class="round_state w-full">
            <b class="border bg-gray-200 row px-8 py-2">Round {{ $round_prefix . ($i + 1) }}</b>
            @foreach ($round as $name => $options)
                @if ($name !== 'eliminated_previously')
                    <div
                        class="border-t row px-2 py-1 {{ $name === 'eliminated' ? 'bg-red-200' : '' }} {{ $name === 'winner' ? 'bg-green-200' : '' }}">
                        <span class="flex-1">
                            {{ $name }}
                        </span>
                        <span class="flex-1 text-right">{{ $options }} </span>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
