@if ($results[$component->id]['results']['result']['conclussive'])
    <div class="d-block text-center bg-green-200 p-3">
        THE WINNER IS {{ $results[$component->id]['results']['result']['conclussive_winner'] }}
    </div>
@else
    <div class="d-block text-center bg-green-200 p-3">
        There is no conclussive winner, the possible outcomes are
        {{ implode(', ', $results[$component->id]['results']['result']['winners']) }}.
    </div>
@endif
<div class="-mx-6 d-flex justify-center flex-wrap overflow-x-scroll">
    @foreach ($results[$component->id]['results']['rounds'] as $i => $round)
        <div class="round">
            @include($component->component_path . '/round', [ 'round' => $round, 'component' => $component, 'i' => $i,
            'round_prefix' => '' ])
        </div>
    @endforeach
</div>
