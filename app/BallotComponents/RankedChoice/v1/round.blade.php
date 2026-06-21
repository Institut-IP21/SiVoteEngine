@php
    // Narrative, auditor-facing round block: count → who's lowest → why they're out
    // → where their ballots went → carry forward. Reads top-to-bottom, mobile-first.
    // Params: $round, $next (next round or null), $i (0-based), $total (round count).
    $next = $next ?? null;
    $total = $total ?? ($i + 1);

    // Option tallies only (arrays/strings — decision, tied, eliminated — are excluded by is_int), high→low.
    $tallies = array_filter($round, fn ($v, $k) => is_int($v) && !in_array($k, ['continuing', 'exhausted', 'exhausted_running'], true), ARRAY_FILTER_USE_BOTH);
    arsort($tallies);

    $continuing = is_int($round['continuing'] ?? null) ? $round['continuing'] : array_sum($tallies);
    $denom = max($continuing, 1);
    $needed = intdiv($continuing, 2) + 1;
    $needPct = min(100, (int) round($needed / $denom * 100));
    $isFinal = ($i + 1) === $total;

    $winner = is_string($round['winner'] ?? null) ? $round['winner'] : null;
    $decision = is_array($round['decision'] ?? null) ? $round['decision'] : ['type' => '', 'tied_among' => [], 'resolved_at_round' => null];
    $elim = ($round['eliminated'] ?? null) !== null ? array_map('trim', explode(',', (string) $round['eliminated'])) : [];
    $elimVotes = array_sum(array_map(fn ($o) => $tallies[$o] ?? 0, $elim));
    $minTally = $tallies !== [] ? min($tallies) : 0;

    // Aggregate transfers = net change of each survivor between this round and the next
    // (secrecy-safe: counts of movement, never per-ballot provenance). Exact + reconcilable
    // only when a single option was eliminated; otherwise shown honestly as net change.
    $transfers = [];
    $exhaustGain = 0;
    $exact = false;
    if ($next !== null && $elim !== []) {
        foreach ($tallies as $name => $v) {
            if (in_array((string) $name, $elim, true)) {
                continue;
            }
            $d = (int) ($next[$name] ?? 0) - $v;
            if ($d > 0) {
                $transfers[$name] = $d;
            }
        }
        $exhaustGain = (int) ($next['exhausted'] ?? 0) - (int) ($round['exhausted'] ?? 0);
        $exact = count($elim) === 1 && (array_sum($transfers) + max($exhaustGain, 0)) === $elimVotes;
    }
@endphp
<div class="rounded-xl border border-line bg-white p-4">
    <div class="flex items-baseline justify-between gap-2">
        <span class="font-bold text-ink">{{ $isFinal ? __('components.rankedchoice.round_final', ['n' => $i + 1]) : __('components.rankedchoice.round_n', ['n' => $i + 1]) }}</span>
        <span class="text-[11px] text-muted">{{ __('components.rankedchoice.ballots_counted', ['n' => $continuing]) }} · {{ __('components.rankedchoice.majority_needed', ['n' => $needed]) }}</span>
    </div>

    {{-- Vote bars with the majority line marked. --}}
    <div class="mt-3 flex flex-col gap-2">
        @foreach ($tallies as $name => $votes)
        @php
            $pct = (int) round($votes / $denom * 100);
            $isWin = $winner !== null && (string) $name === (string) $winner;
            $isOut = in_array((string) $name, $elim, true);
            $fill = $isWin ? 'var(--color-secure)' : ($isOut ? 'var(--color-danger)' : 'var(--color-brand)');
        @endphp
        <div>
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="text-ink {{ $isWin ? 'font-bold' : 'font-medium' }}" style="overflow-wrap:anywhere">
                    {{ $name }}@if ($isWin) <span style="color:var(--color-secure)">✓</span>@elseif ($isOut) <span style="color:var(--color-danger)">✕</span>@endif
                </span>
                <span class="flex-shrink-0 text-muted"><span class="font-semibold text-ink">{{ $pct }}%</span> · {{ $votes }}</span>
            </div>
            <div class="relative mt-1 h-2.5 w-full rounded-full overflow-hidden" style="background: var(--color-canvas); border: 1px solid var(--color-line)">
                <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $fill }}"></div>
                <span class="absolute top-0 bottom-0" style="left: {{ $needPct }}%; width:2px; background: var(--color-ink); opacity:.35" aria-hidden="true"></span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Why this round resolved as it did (the rationale the engine records). --}}
    @php
        $tiedNames = implode(', ', $decision['tied_among'] ?? []);
        $elimNames = implode(', ', $elim);
    @endphp
    <p class="mt-3 text-[13px] leading-relaxed" style="color: var(--color-ink)">
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
    </p>

    {{-- Where the eliminated ballots went (aggregate net movement). --}}
    @if ($transfers !== [] || ($elim !== [] && $exhaustGain > 0))
    <div class="mt-3 rounded-lg p-3" style="background: var(--color-canvas)">
        <p class="text-[11px] uppercase tracking-[0.06em] font-bold text-muted">
            {{ $exact ? __('components.rankedchoice.transfer_exact', ['name' => $elimNames, 'votes' => $elimVotes]) : __('components.rankedchoice.transfer_net', ['names' => $elimNames]) }}
        </p>
        <div class="mt-1.5 flex flex-col gap-1 text-sm">
            @foreach ($transfers as $name => $d)
            <div class="flex items-baseline justify-between gap-3">
                <span class="text-ink" style="overflow-wrap:anywhere">→ {{ $name }}</span>
                <span class="flex-shrink-0 font-semibold" style="color: var(--color-secure)">+{{ $d }}</span>
            </div>
            @endforeach
            @if ($exhaustGain > 0)
            <div class="flex items-baseline justify-between gap-3 text-muted">
                <span>→ {{ __('components.rankedchoice.exhausted') }} <span class="text-[11px]">({{ __('components.rankedchoice.no_next_choice') }})</span></span>
                <span class="flex-shrink-0 font-semibold">+{{ $exhaustGain }}</span>
            </div>
            @endif
        </div>
        @if ($exact)
        <p class="mt-1.5 text-[11px] text-muted">{{ __('components.rankedchoice.transfer_reconciles', ['n' => $elimVotes]) }}</p>
        @endif
    </div>
    @endif

    {{-- Final-round exhausted footnote. --}}
    @if ($isFinal && (int) ($round['exhausted'] ?? 0) > 0)
    <p class="mt-3 text-[11px] text-muted leading-relaxed">{{ __('components.rankedchoice.exhausted_footnote', ['n' => (int) $round['exhausted']]) }}</p>
    @endif
</div>
