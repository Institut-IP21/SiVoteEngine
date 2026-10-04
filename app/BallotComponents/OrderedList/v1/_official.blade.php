@php
    // The OFFICIAL list when a binding quota decides the seats: one row per
    // seat in the quota slate (alternation / min / max applied), never the
    // votes-alone order -- that one follows below, for comparison only.
    // A seat no tie resolution agrees on reads "not yet decided", and each
    // open tie is spelled out under the list. Params: $res, $component.
    $corrected = $res['corrected'];
    $ranking = $res['ranking'];
    /** @var array<string, string> $categories */
    $categories = is_array($component->settings['categories'] ?? null) ? $component->settings['categories'] : [];
    $diffReasons = collect($corrected['diff'])->pluck('reason', 'candidate')->all();

    $seatCount = min($res['seats'], count($ranking));
    $byPosition = array_flip($res['official_positions']);
    $seatRows = [];
    for ($seat = 1; $seat <= $seatCount; $seat++) {
        $seatRows[$seat] = $corrected['provisional'] ? ($byPosition[$seat] ?? null) : ($res['official_order'][$seat - 1] ?? null);
    }

    // Everyone with no seat in any resolution, in votes order.
    $inPlay = $corrected['provisional'] ? [...$corrected['seated'], ...$corrected['contested']] : $res['official_order'];
    $notElected = array_values(array_filter(
        array_map(static fn (array $entry): string => (string) $entry['candidate'], $ranking),
        static fn (string $c): bool => ! in_array($c, $inPlay, true)
    ));
    $stillTied = $corrected['provisional']
        ? array_values(array_merge(array_diff($corrected['seated'], array_keys($res['official_positions'])), $corrected['contested']))
        : [];
    $ties = $corrected['scenarios'] ?? null;
@endphp
<ol class="flex flex-col gap-1.5 m-0 p-0 list-none">
    @foreach ($seatRows as $seat => $name)
        <li @class([
            'flex items-center gap-2.5 rounded-lg px-2.5 py-1.5 -mx-2.5',
            'bg-secure-soft' => $name !== null,
        ])>
            <span class="inline-flex items-center justify-center flex-shrink-0 w-6 h-6 rounded-full text-[11px] font-bold" style="border:1px solid var(--color-line); background:#fff; color:var(--color-ink)">{{ $seat }}</span>
            @if ($name === null)
                <span class="flex-1 text-muted font-medium">{{ __('components.orderedlist.seat_undecided') }}</span>
            @else
                <span class="flex-1 text-ink font-semibold" style="overflow-wrap:anywhere">{{ $name }}</span>
                @if (isset($diffReasons[$name]))
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full text-muted" style="background:var(--color-canvas)">{{ $diffReasons[$name] === 'alternate' ? __('components.orderedlist.alternated') : __('components.orderedlist.promoted') }}</span>
                @endif
                @if (($categories[$name] ?? null) !== null)
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full text-muted" style="background:var(--color-canvas)">{{ $categories[$name] }}</span>
                @endif
            @endif
        </li>
    @endforeach
</ol>

@if (! empty($ties))
    @include($component->component_path . '/_ties', ['ties' => $ties])
@elseif ($stillTied !== [])
    <p class="mt-2 text-[12px] text-muted" style="overflow-wrap:anywhere">{{ __('components.orderedlist.still_tied_for_open_seats', ['names' => implode(', ', $stillTied)]) }}</p>
@endif

@if ($corrected['too_complex'] ?? false)
    <p class="mt-2 text-[12px] text-muted">{{ __('components.orderedlist.quota_too_complex') }}</p>
@elseif ($corrected['provisional'])
    <p class="mt-2 text-[12px] text-muted">{{ __('components.orderedlist.quota_provisional') }}</p>
@endif
@if ($corrected['partly_infeasible'] ?? false)
    <p class="mt-2 text-[12px] text-muted">{{ __('components.orderedlist.quota_partly_infeasible') }}</p>
@endif

@if ($notElected !== [])
    <div class="mt-3 mb-2 flex items-center gap-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted" aria-hidden="true">
        <span class="flex-1 border-t border-dashed border-line"></span>
        <span>{{ __('components.orderedlist.cutoff_note') }}</span>
        <span class="flex-1 border-t border-dashed border-line"></span>
    </div>
    <ul class="flex flex-col gap-1 m-0 p-0 list-none">
        @foreach ($notElected as $name)
            <li class="flex items-center gap-2.5 px-2.5 py-1 -mx-2.5 text-muted">
                <span class="flex-1 font-medium" style="overflow-wrap:anywhere">{{ $name }}</span>
                @if (($categories[$name] ?? null) !== null)
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full" style="background:var(--color-canvas)">{{ $categories[$name] }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
