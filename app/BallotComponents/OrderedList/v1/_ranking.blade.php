@php
    // The full N-position list, in ranking order (Positions elected AND not —
    // transparency over the whole roster, consistent with publishing the full
    // pairwise matrix). Determined candidates render as single numbered rows;
    // an unresolved band renders as a grouped, tinted block spanning its
    // position range -- surfaced as-is, since the engine never resolves a
    // genuine tie itself. Params: $res (OrderedListResult::toArray()),
    // $component, optional $compact (true when this is only the votes-alone
    // comparison under an official quota list: smaller, muted, no tint).
    $compact = $compact ?? false;
    $ranking = $res['ranking'];
    $bands = $res['bands'];
    $seats = $res['seats'];
    $total = count($ranking);
    /** @var array<string, string> $categories */
    $categories = is_array($component->settings['categories'] ?? null) ? $component->settings['categories'] : [];

    $bandOf = [];
    foreach ($bands as $i => $band) {
        foreach ($band['candidates'] as $c) {
            $bandOf[$c] = $i;
        }
    }

    // Build one display row per position/cluster, walking the ranking once so
    // band members (always contiguous, by construction) collapse into a
    // single grouped row.
    $rows = [];
    $seenBand = [];
    foreach ($ranking as $idx => $entry) {
        $bandIndex = $bandOf[$entry['candidate']] ?? null;

        if ($bandIndex === null) {
            $rows[] = ['kind' => 'single', 'candidate' => $entry['candidate'], 'position' => $idx + 1, 'status' => $entry['status']];
            continue;
        }

        if (isset($seenBand[$bandIndex])) {
            continue;
        }
        $seenBand[$bandIndex] = true;
        $rows[] = ['kind' => 'band', 'band' => $bands[$bandIndex]];
    }

    // Where to drop the plain "seat cutoff" divider: right after the row that
    // completes exactly $seats emitted positions, when that boundary falls
    // cleanly between two rows (not mid-band — an unresolved band straddling
    // the cutoff already carries its own "tied" styling).
    $cutoffAfterIndex = null;
    $emitted = 0;
    foreach ($rows as $i => $row) {
        $emitted += match ($row['kind']) {
            'single' => 1,
            'band' => count($row['band']['candidates']),
        };
        if ($emitted === $seats && $i < count($rows) - 1) {
            $cutoffAfterIndex = $i;
        }
        if ($emitted >= $seats) {
            break;
        }
    }
@endphp
<ol class="flex flex-col gap-1.5 m-0 p-0 list-none">
    @foreach ($rows as $i => $row)
        @if ($row['kind'] === 'single')
            <li @class([
                'flex items-center gap-2.5 rounded-lg -mx-2.5',
                'px-2.5 py-1.5' => ! $compact,
                'px-2.5 py-0.5 text-[13px]' => $compact,
                'bg-secure-soft' => $row['status'] === 'elected' && ! $compact,
            ])>
                <span class="inline-flex items-center justify-center flex-shrink-0 w-6 h-6 rounded-full text-[11px] font-bold" style="border:1px solid var(--color-line); background:#fff; color:var(--color-ink)"
                    aria-label="{{ __('components.orderedlist.position', ['name' => $row['candidate'], 'pos' => $row['position'], 'total' => $total]) }}">{{ $row['position'] }}</span>
                <span class="flex-1 {{ $compact ? 'text-muted' : 'text-ink' }} {{ $row['status'] === 'elected' && ! $compact ? 'font-semibold' : 'font-medium' }}" style="overflow-wrap:anywhere">{{ $row['candidate'] }}</span>
                @if (($categories[$row['candidate']] ?? null) !== null)
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full text-muted" style="background:var(--color-canvas)">{{ $categories[$row['candidate']] }}</span>
                @endif
            </li>
        @else
            <li class="rounded-lg px-2.5 -mx-2.5 {{ $compact ? 'py-1 text-muted' : 'py-2 bg-warn-soft' }}">
                <p class="mb-1.5 text-[12px] font-semibold {{ $compact ? 'text-muted' : 'text-warn-fg' }}">
                    {{ __('components.orderedlist.band_span', ['from' => $row['band']['span'][0], 'to' => $row['band']['span'][1]]) }}
                    — {{ __('components.orderedlist.tie_awaiting') }}
                </p>
                @php
                    // A band straddling the cutoff: say how many of its members get in
                    // -- by votes alone, so not when a binding quota decides the seats.
                    $seatsLeft = ($res['official'] ?? 'natural') === 'natural' ? $seats - $row['band']['span'][0] + 1 : 0;
                @endphp
                @if ($seatsLeft > 0 && $seatsLeft < count($row['band']['candidates']))
                    <p class="mb-1.5 text-[12px] text-warn-fg">{{ trans_choice('components.orderedlist.band_seats_left', $seatsLeft, ['count' => $seatsLeft]) }}</p>
                @endif
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($row['band']['candidates'] as $c)
                        <span class="text-[12px] font-medium px-2 py-0.5 rounded-full text-ink" style="background:#fff; border:1px solid var(--color-line)">{{ $c }}</span>
                    @endforeach
                </div>
            </li>
        @endif

        @if ($i === $cutoffAfterIndex)
            <li class="my-1 flex items-center gap-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted" aria-hidden="true">
                <span class="flex-1 border-t border-dashed border-line"></span>
                <span>{{ __('components.orderedlist.cutoff_note') }}</span>
                <span class="flex-1 border-t border-dashed border-line"></span>
            </li>
        @endif
    @endforeach
</ol>
