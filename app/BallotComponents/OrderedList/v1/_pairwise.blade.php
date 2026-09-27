@php
    // Full row-vs-column pairwise matrix (bulletin-board disclosure — secrecy
    // here is unlinkability, not confidentiality, so publishing this in full
    // is correct). Params: $pairwise (['candidates' => list<string>, 'matrix'
    // => array<string,array<string,int>>]).
    $candidates = $pairwise['candidates'];
    $matrix = $pairwise['matrix'];
@endphp
<div class="overflow-x-auto" style="border:1px solid var(--color-line); border-radius:.75rem">
    <table class="w-full border-collapse text-sm" style="white-space:nowrap">
        <caption class="sr-only">{{ __('components.orderedlist.pairwise_hint') }}</caption>
        <thead>
            <tr style="background: var(--color-canvas)">
                <th scope="col" class="text-left px-3 py-2" style="position:sticky; left:0; background: var(--color-canvas)">{{ __('components.orderedlist.candidate') }}</th>
                @foreach ($candidates as $col)
                    <th scope="col" class="px-3 py-2 text-right font-semibold" style="overflow-wrap:anywhere; white-space:normal; min-width:5rem">{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($candidates as $row)
                <tr style="border-top:1px solid var(--color-line)">
                    <th scope="row" class="text-left px-3 py-2 font-medium text-ink" style="position:sticky; left:0; background:#fff; overflow-wrap:anywhere; white-space:normal; min-width:7rem">{{ $row }}</th>
                    @foreach ($candidates as $col)
                        <td class="px-3 py-2 text-right {{ $row === $col ? 'text-muted' : 'text-ink' }}">
                            {{ $row === $col ? '—' : ($matrix[$row][$col] ?? 0) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
