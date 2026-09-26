@php
    // The full N-position list, in ranking order (Positions elected AND not —
    // transparency over the whole roster, consistent with publishing the full
    // pairwise matrix). Determined candidates render as single numbered rows;
    // an unresolved band renders as a grouped, tinted block spanning its
    // position range; a band the runner HAS resolved (present + complete in
    // $res['final']) renders as individually-numbered rows with a small
    // "Runner announced" note. Params: $res (OrderedListResult::toArray()),
    // $component.
    $ranking = $res['ranking'];
    $bands = $res['bands'];
    $final = $res['final'];
    $resolutions = $res['resolutions'];
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

    $finalByCandidate = [];
    if ($final !== null) {
        foreach ($final['order'] as $entry) {
            $finalByCandidate[$entry['candidate']] = $entry;
        }
    }

    $signature = function (array $names): string {
        $sorted = $names;
        sort($sorted);
        return implode("\x01", $sorted);
    };

    $resolutionByBandIndex = [];
    foreach ($bands as $i => $band) {
        $bandSig = $signature($band['candidates']);
        foreach ($resolutions as $resolution) {
            if ($signature($resolution['cluster']) === $bandSig) {
                $resolutionByBandIndex[$i] = $resolution;
            }
        }
    }

    // Build one display row per position/cluster, walking the ranking once so
    // band members (always contiguous, by construction) collapse into a
    // single grouped or resolved row.
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
        $band = $bands[$bandIndex];

        $resolved = $final !== null && collect($band['candidates'])->every(
            fn ($m) => ($finalByCandidate[$m]['tied'] ?? true) === false
        );

        if ($resolved) {
            $members = collect($band['candidates'])
                ->map(fn ($m) => ['candidate' => $m, 'position' => $finalByCandidate[$m]['position']])
                ->sortBy('position')
                ->values()
                ->all();
            $rows[] = [
                'kind' => 'resolved_band',
                'members' => $members,
                'comment' => $resolutionByBandIndex[$bandIndex]['comment'] ?? null,
            ];
        } else {
            $rows[] = ['kind' => 'band', 'band' => $band];
        }
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
            'resolved_band' => count($row['members']),
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
                'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 -mx-2.5',
                'bg-secure-soft' => $row['status'] === 'elected',
            ])>
                <span class="inline-flex items-center justify-center flex-shrink-0 w-6 h-6 rounded-full text-[11px] font-bold" style="border:1px solid var(--color-line); background:#fff; color:var(--color-ink)"
                    aria-label="{{ __('components.orderedlist.position', ['name' => $row['candidate'], 'pos' => $row['position'], 'total' => $total]) }}">{{ $row['position'] }}</span>
                <span class="flex-1 text-ink {{ $row['status'] === 'elected' ? 'font-semibold' : 'font-medium' }}" style="overflow-wrap:anywhere">{{ $row['candidate'] }}</span>
                @if (($categories[$row['candidate']] ?? null) !== null)
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full text-muted" style="background:var(--color-canvas)">{{ $categories[$row['candidate']] }}</span>
                @endif
            </li>
        @elseif ($row['kind'] === 'resolved_band')
            @foreach ($row['members'] as $m)
                <li class="flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 -mx-2.5 bg-secure-soft">
                    <span class="inline-flex items-center justify-center flex-shrink-0 w-6 h-6 rounded-full text-[11px] font-bold" style="border:1px solid var(--color-line); background:#fff; color:var(--color-ink)"
                        aria-label="{{ __('components.orderedlist.position', ['name' => $m['candidate'], 'pos' => $m['position'], 'total' => $total]) }}">{{ $m['position'] }}</span>
                    <span class="flex-1 font-semibold text-ink" style="overflow-wrap:anywhere">{{ $m['candidate'] }}</span>
                </li>
            @endforeach
            @if ($row['comment'])
                <li class="px-2.5 -mx-2.5 text-[12px] text-muted italic">{{ __('components.orderedlist.runner_announced', ['comment' => $row['comment']]) }}</li>
            @endif
        @else
            <li class="rounded-lg px-2.5 py-2 -mx-2.5 bg-warn-soft">
                <p class="mb-1.5 text-[12px] font-semibold text-warn-fg">
                    {{ __('components.orderedlist.band_span', ['from' => $row['band']['span'][0], 'to' => $row['band']['span'][1]]) }}
                    — {{ __('components.orderedlist.tie_awaiting') }}
                </p>
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
