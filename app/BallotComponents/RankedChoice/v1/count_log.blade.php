@php
    // Plain-text audit log under the tabulation: one line per round spelling out the
    // engine-recorded decision — which option was eliminated and why (clear last place,
    // zero-vote batch, or a look-back tie-break and the round that resolved it), the
    // exhausted-ballot movements, and the final winner/tie. Params: $rounds, $component.
    $isTally = fn ($v, $k) => is_int($v) && !in_array($k, ['continuing', 'exhausted', 'exhausted_running'], true);
    $prevExhausted = 0;
@endphp
<ol class="flex flex-col gap-2 text-[13px] leading-relaxed" style="color: var(--color-ink)">
    @foreach ($rounds as $i => $round)
    @php
        $tallies = array_filter($round, $isTally, ARRAY_FILTER_USE_BOTH);
        $continuing = is_int($round['continuing'] ?? null) ? $round['continuing'] : array_sum($tallies);
        $needed = intdiv($continuing, 2) + 1;
        $decision = is_array($round['decision'] ?? null) ? $round['decision'] : ['type' => '', 'tied_among' => [], 'resolved_at_round' => null];
        $elim = ($round['eliminated'] ?? null) !== null ? array_map('trim', explode(',', (string) $round['eliminated'])) : [];
        $elimNames = implode(', ', $elim);
        $tiedNames = implode(', ', $decision['tied_among'] ?? []);
        $winner = is_string($round['winner'] ?? null) ? $round['winner'] : null;
        $minTally = $tallies !== [] ? min($tallies) : 0;
        $exhausted = (int) ($round['exhausted'] ?? 0);
        $exhaustedDelta = $exhausted - $prevExhausted;
        $prevExhausted = $exhausted;
    @endphp
    <li class="flex gap-2">
        <span class="font-bold text-muted flex-shrink-0">{{ __('components.rankedchoice.round') }} {{ $i + 1 }}</span>
        <span style="overflow-wrap:anywhere">
            @switch($decision['type'])
                @case('majority')
                    {{ __('components.rankedchoice.why_majority', ['name' => $winner, 'votes' => $tallies[$winner] ?? 0, 'needed' => $needed]) }}
                    @break
                @case('final_two')
                    {{ __('components.rankedchoice.why_final_two', ['name' => $winner, 'votes' => $tallies[$winner] ?? 0]) }}
                    @break
                @case('lastplace')
                    {{ __('components.rankedchoice.why_lastplace', ['name' => $elimNames, 'votes' => $minTally]) }}
                    @break
                @case('zerobatch')
                    {{ __('components.rankedchoice.why_zerobatch', ['names' => $elimNames]) }}
                    @break
                @case('lookback')
                    {{ __('components.rankedchoice.why_lookback', ['names' => $tiedNames, 'votes' => $minTally, 'round' => $decision['resolved_at_round'], 'loser' => $elimNames]) }}
                    @break
                @case('tie')
                    {{ __('components.rankedchoice.why_tie', ['names' => $tiedNames !== '' ? $tiedNames : implode(', ', array_keys($tallies))]) }}
                    @break
                @case('exhausted_all')
                    {{ __('components.rankedchoice.why_exhausted_all') }}
                    @break
            @endswitch
            @if ($exhaustedDelta > 0)
                <span class="text-muted">{{ __('components.rankedchoice.log_exhausted', ['n' => $exhaustedDelta]) }}</span>
            @endif
        </span>
    </li>
    @endforeach
</ol>
