@php
    // One block per open tie: each way it can go, who then takes which seat,
    // who misses out (engine-computed, exact -- QuotaCorrector::scenarios()).
    // Params: $ties, $component.
    $seatList = static fn (array $seats): string => count($seats) > 1 && $seats === range($seats[0], $seats[0] + count($seats) - 1)
        ? $seats[0] . '–' . $seats[count($seats) - 1]
        : implode(', ', $seats);
    $names = static fn (array $list): string => count($list) > 1
        ? implode(', ', array_slice($list, 0, -1)) . __('components.orderedlist.tie_names_and') . $list[count($list) - 1]
        : implode('', $list);
    $condition = static fn (array $when): string => __('components.orderedlist.tie_if', ['clauses' => implode(__('components.orderedlist.tie_clause_join'), array_map(
        static fn (array $w): string => __('components.orderedlist.tie_clause', ['ahead' => $w['ahead'], 'behind' => $names($w['behind'])]),
        $when
    ))]);
@endphp
@foreach ($ties as $tie)
    <div class="mt-3 rounded-lg px-2.5 py-2 bg-warn-soft">
        <p class="mb-1.5 text-[12px] font-semibold text-warn-fg">{{ trans_choice('components.orderedlist.tie_heading', count($tie['seats']), ['seats' => $seatList($tie['seats'])]) }}</p>
        <ul class="flex flex-col gap-2 m-0 p-0 list-none">
            @foreach ($tie['options'] as $option)
                <li class="text-[12px]" style="overflow-wrap:anywhere">
                    <p class="m-0 font-medium text-ink">{{ $option['when'] !== [] ? $condition($option['when']) : __('components.orderedlist.tie_otherwise') }}:</p>
                    <p class="m-0 text-ink">
                        @foreach ($option['seats'] as $seat => $name)
                            <span class="whitespace-nowrap">{{ $seat }}. {{ $name }}</span>@if (! $loop->last)<span class="text-muted"> · </span>@endif
                        @endforeach
                    </p>
                    @if ($option['out'] !== [])
                        <p class="m-0 text-muted">{{ __('components.orderedlist.tie_out', ['names' => implode(', ', $option['out'])]) }}</p>
                    @endif
                    @if ($option['infeasible'])
                        <p class="m-0 text-muted">{{ __('components.orderedlist.tie_option_infeasible') }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endforeach
