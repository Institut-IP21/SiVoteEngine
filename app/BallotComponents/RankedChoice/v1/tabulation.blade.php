@php
    // Canonical electoral-commission tabulation: candidates as rows, rounds as columns,
    // read left→right to watch votes flow. A dense cross-check for the narrative cards.
    // Params: $rounds (full list), $component.
    $isTally = fn ($v, $k) => is_int($v) && !in_array($k, ['continuing', 'exhausted', 'exhausted_running'], true);
    // Roster + order = round 0's option keys (every option present before any elimination).
    $roster = $rounds === [] ? [] : array_keys(array_filter($rounds[0], $isTally, ARRAY_FILTER_USE_BOTH));

    // Per-(name, round) view: value, net delta vs the previous round it was present in, state.
    $cells = [];
    foreach ($roster as $name) {
        $prev = null;
        foreach ($rounds as $r => $round) {
            $present = array_key_exists($name, $round) && is_int($round[$name]);
            $v = $present ? $round[$name] : null;
            $elim = ($round['eliminated'] ?? null) !== null ? array_map('trim', explode(',', (string) $round['eliminated'])) : [];
            $state = match (true) {
                ($round['winner'] ?? null) === $name => 'winner',
                in_array($name, $round['tied'] ?? [], true) => 'tied',
                in_array((string) $name, $elim, true) => 'out',
                !$present => 'gone',
                default => 'active',
            };
            $cells[$name][$r] = [
                'v' => $v,
                'delta' => ($present && is_int($prev)) ? $v - $prev : null,
                'state' => $state,
            ];
            if ($present) {
                $prev = $v;
            }
        }
    }

    $foot = [];
    foreach ($rounds as $r => $round) {
        $cont = is_int($round['continuing'] ?? null) ? $round['continuing'] : 0;
        $tallies = array_filter($round, $isTally, ARRAY_FILTER_USE_BOTH);
        $foot[$r] = [
            'threshold' => intdiv($cont, 2) + 1,
            'continuing' => $cont,
            'exhausted' => (int) ($round['exhausted'] ?? 0),
            'reconciles' => array_sum($tallies) === $cont,
        ];
    }
    $bg = ['winner' => 'var(--color-secure-soft)', 'out' => 'var(--color-danger-soft)', 'tied' => 'var(--color-warn-soft)', 'gone' => 'var(--color-canvas)', 'active' => '#fff'];
@endphp
<div class="overflow-x-auto" style="border:1px solid var(--color-line); border-radius:.75rem">
    <table class="w-full border-collapse text-sm" style="white-space:nowrap">
        <caption class="sr-only">{{ __('components.rankedchoice.matrix_caption') }}</caption>
        <thead>
            <tr style="background: var(--color-canvas)">
                <th scope="col" class="text-left px-3 py-2" style="position:sticky; left:0; background: var(--color-canvas)">{{ __('components.rankedchoice.candidate') }}</th>
                @foreach ($rounds as $r => $_round)
                <th scope="col" class="px-3 py-2 text-right font-semibold">{{ __('components.rankedchoice.round') }} {{ $r + 1 }}</th>
                @endforeach
            </tr>
            <tr class="text-muted text-[12px]">
                <th scope="row" class="text-left px-3 py-1 font-medium" style="position:sticky; left:0; background:#fff">{{ __('components.rankedchoice.majority_needed_short') }}</th>
                @foreach ($foot as $f)
                <td class="px-3 py-1 text-right">{{ $f['threshold'] }}</td>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($roster as $name)
            <tr style="border-top:1px solid var(--color-line)">
                <th scope="row" class="text-left px-3 py-2 font-medium text-ink" style="position:sticky; left:0; background:#fff; overflow-wrap:anywhere; white-space:normal; min-width:7rem">{{ $name }}</th>
                @foreach ($cells[$name] as $r => $c)
                <td class="px-3 py-2 text-right" style="background: {{ $bg[$c['state']] }}">
                    @if ($c['v'] !== null)
                        <span class="font-semibold text-ink">{{ $c['v'] }}</span>
                        @if ($c['state'] === 'winner')<span style="color:var(--color-secure)"> ✓</span><span class="sr-only">{{ __('components.winner') }}</span>@endif
                        @if ($c['state'] === 'out')<span style="color:var(--color-danger)"> ✕</span><span class="sr-only">{{ __('components.eliminated') }}</span>@endif
                        @if ($c['delta'] !== null)<span class="block text-[11px] text-muted">{{ $c['delta'] >= 0 ? '+' : '−' }}{{ abs($c['delta']) }}</span>@endif
                    @else
                        <span class="text-muted" aria-label="{{ __('components.rankedchoice.out_of_count') }}">—</span>
                    @endif
                </td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
        <tfoot class="text-muted text-[12px]">
            <tr style="border-top:1px solid var(--color-line)">
                <th scope="row" class="text-left px-3 py-1 font-medium" style="position:sticky; left:0; background:#fff">{{ __('components.rankedchoice.continuing') }}</th>
                @foreach ($foot as $f)<td class="px-3 py-1 text-right">{{ $f['continuing'] }}</td>@endforeach
            </tr>
            <tr>
                <th scope="row" class="text-left px-3 py-1 font-medium" style="position:sticky; left:0; background:#fff">{{ __('components.rankedchoice.exhausted') }}</th>
                @foreach ($foot as $f)<td class="px-3 py-1 text-right">{{ $f['exhausted'] }}</td>@endforeach
            </tr>
        </tfoot>
    </table>
</div>
