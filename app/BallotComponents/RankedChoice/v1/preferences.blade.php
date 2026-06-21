@php
    // First-preference position matrix (option × rank): how many voters placed each
    // option 1st, 2nd, 3rd… An independent cross-check on the round-1 tally that uses
    // only secrecy-safe MARGINALS (per-position counts, never full per-ballot rankings).
    // Params: $preferences (option => [pos => count]), $component.
    $prefs = is_array($preferences ?? null) ? $preferences : [];
    $positions = 0;
    foreach ($prefs as $row) {
        if (is_array($row)) {
            $positions = max($positions, count($row));
        }
    }
@endphp
@if ($prefs !== [] && $positions > 0)
<div class="overflow-x-auto" style="border:1px solid var(--color-line); border-radius:.75rem">
    <table class="w-full border-collapse text-sm" style="white-space:nowrap">
        <caption class="sr-only">{{ __('components.rankedchoice.preferences_caption') }}</caption>
        <thead>
            <tr style="background: var(--color-canvas)">
                <th scope="col" class="text-left px-3 py-2" style="position:sticky; left:0; background: var(--color-canvas)">{{ __('components.rankedchoice.candidate') }}</th>
                @for ($p = 0; $p < $positions; $p++)
                <th scope="col" class="px-3 py-2 text-right font-semibold">{{ __('components.rankedchoice.nth_choice', ['n' => $p + 1]) }}</th>
                @endfor
            </tr>
        </thead>
        <tbody>
            @foreach ($prefs as $name => $row)
            <tr style="border-top:1px solid var(--color-line)">
                <th scope="row" class="text-left px-3 py-2 font-medium text-ink" style="position:sticky; left:0; background:#fff; overflow-wrap:anywhere; white-space:normal; min-width:7rem">{{ $name }}</th>
                @for ($p = 0; $p < $positions; $p++)
                <td class="px-3 py-2 text-right {{ ($row[$p] ?? 0) === 0 ? 'text-muted' : 'text-ink' }}">{{ $row[$p] ?? 0 }}</td>
                @endfor
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
