@php
    // Two side-by-side columns: the order by votes alone, and the order with
    // the settings.quota category balancing (min/max, or alternation)
    // applied. Whichever one is the OFFICIAL result ($res['official'])
    // carries the "Official result" badge — never a colour-coded verdict on
    // the other column, since an advisory quota is not wrong, just not
    // binding. Params: $res, $component.
    $elected = $res['elected'];
    $corrected = $res['corrected'];
    $diffReasons = collect($corrected['diff'])->pluck('reason', 'candidate')->all();
    $promoted = array_keys($diffReasons);
    $official = $res['official'];
    $isAlternate = ($component->settings['quota']['type'] ?? null) === 'alternate';
    $withQuotaHeading = $isAlternate ? __('components.orderedlist.with_alternation') : __('components.orderedlist.with_quota');

    // Seat rows for the quota column: a certain occupant per seat where
    // every tie resolution agrees, otherwise "undecided". Candidates who are
    // surely seated but not at a certain seat, and candidates who may or may
    // not be seated, are listed together as still tied for the open seats.
    $seatCount = $corrected['provisional'] ? min($res['seats'], count($res['ranking'])) : count($corrected['order']);
    $byPosition = array_flip($corrected['positions']);
    $seatRows = [];
    for ($seat = 1; $seat <= $seatCount; $seat++) {
        $seatRows[$seat] = $corrected['provisional'] ? ($byPosition[$seat] ?? null) : ($corrected['order'][$seat - 1] ?? null);
    }
    $stillTied = $corrected['provisional']
        ? array_values(array_merge(array_diff($corrected['seated'], array_keys($corrected['positions'])), $corrected['contested']))
        : [];

    $ties = $corrected['scenarios'] ?? null;
@endphp
<div class="mt-4 grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))">
    <div class="rounded-xl p-3" style="border:1px solid var(--color-line)">
        <div class="flex items-center justify-between gap-2 mb-2">
            <p class="text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.orderedlist.by_votes_alone') }}</p>
            @if ($official === 'natural')
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-secure-soft text-secure">{{ __('components.orderedlist.official_badge') }}</span>
            @endif
        </div>
        <ol class="flex flex-col gap-1 m-0 p-0 list-none text-sm">
            @foreach ($elected as $i => $name)
                <li class="text-ink" style="overflow-wrap:anywhere">{{ $i + 1 }}. {{ $name }}</li>
            @endforeach
        </ol>
    </div>
    <div class="rounded-xl p-3" style="border:1px solid var(--color-line)">
        <div class="flex items-center justify-between gap-2 mb-2">
            <p class="text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ $withQuotaHeading }}</p>
            @if ($official === 'corrected')
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-secure-soft text-secure">{{ __('components.orderedlist.official_badge') }}</span>
            @endif
        </div>
        <ol class="flex flex-col gap-1 m-0 p-0 list-none text-sm">
            {{-- One row per seat: its certain occupant, or "undecided" while a
                 genuine tie still decides it (never a guessed name). --}}
            @foreach ($seatRows as $seat => $name)
                <li class="{{ $name === null ? 'text-muted' : 'text-ink' }}" style="overflow-wrap:anywhere">
                    {{ $seat }}. {{ $name ?? __('components.orderedlist.seat_undecided') }}
                    @if ($name !== null && in_array($name, $promoted, true))
                        <span class="ml-1 text-[11px] font-semibold px-2 py-0.5 rounded-full text-muted" style="background:var(--color-canvas)">{{ ($diffReasons[$name] ?? null) === 'alternate' ? __('components.orderedlist.alternated') : __('components.orderedlist.promoted') }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
        @if (! empty($ties))
            @include($component->component_path . '/_ties', ['ties' => $ties])
        @elseif ($stillTied !== [])
            <p class="mt-2 text-[12px] text-muted" style="overflow-wrap:anywhere">{{ __('components.orderedlist.still_tied_for_open_seats', ['names' => implode(', ', $stillTied)]) }}</p>
        @endif
        @if (! $corrected['binding'])
            <p class="mt-2 text-[12px] text-muted">{{ __('components.orderedlist.quota_binding_note') }}</p>
        @endif
        @if ($corrected['infeasible'])
            <p class="mt-2 text-[12px] text-muted">{{ __('components.orderedlist.quota_infeasible') }}</p>
        @elseif ($corrected['too_complex'] ?? false)
            <p class="mt-2 text-[12px] text-muted">{{ __('components.orderedlist.quota_too_complex') }}</p>
        @elseif ($corrected['provisional'])
            <p class="mt-2 text-[12px] text-muted">{{ __('components.orderedlist.quota_provisional') }}</p>
        @endif
        @if ($corrected['partly_infeasible'] ?? false)
            <p class="mt-2 text-[12px] text-muted">{{ __('components.orderedlist.quota_partly_infeasible') }}</p>
        @endif
    </div>
</div>
