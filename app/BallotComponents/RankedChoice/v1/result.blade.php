<div class="something" x-data="{ show_elimination: false }">
    @if ($results[$component->id]['results']['result']['conclussive'])
    <button class="w-full text-center bg-green-200 hover:bg-green-400 p-3"
        x-on:click="show_elimination = !show_elimination">
        {{ __('components.rankedchoice.winner_is') }}
        {{ $results[$component->id]['results']['result']['conclussive_winner'] }}
    </button>
    @else
    <button class="w-full text-center bg-yellow-200 hover:bg-yellow-400 p-3"
        x-on:click="show_elimination = !show_elimination">
        {{ __('components.rankedchoice.no_winner') }}
        {{ implode(', ', $results[$component->id]['results']['result']['winners']) }}.
    </button>
    @endif
    <div class="flex justify-start overflow-x-scroll" x-show="show_elimination" style="display: none;">
        @foreach ($results[$component->id]['results']['rounds'] as $i => $round)
        @include($component->component_path . '/round', [ 'round' => $round, 'component' => $component, 'i' =>
        $i,
        'round_prefix' => '' ])
        @endforeach
    </div>
</div>