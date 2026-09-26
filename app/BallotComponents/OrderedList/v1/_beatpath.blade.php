@php
    // Schulze beatpath disclosure (bulletin-board transparency, same
    // rationale as the pairwise matrix): the widest-path strength matrix
    // p[row][col] -- the strength of the strongest chain of consecutive
    // pairwise wins from the row option to the column option, "—" when no
    // such chain exists -- followed by a plain-language "why A outranks B"
    // line per strict relation, strongest first. Params: $beatpath
    // (['strength' => array<string,array<string,int|null>>, 'winners' =>
    // list<array{winner,loser,strength,path}>]), $candidates (list<string>,
    // row/column order).
    $strength = $beatpath['strength'];
    $winners = $beatpath['winners'];
@endphp
<div class="overflow-x-auto" style="border:1px solid var(--color-line); border-radius:.75rem">
    <table class="w-full border-collapse text-sm" style="white-space:nowrap">
        <caption class="sr-only">{{ __('components.orderedlist.beatpath_hint') }}</caption>
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
                            {{ $row === $col ? '—' : ($strength[$row][$col] ?? '—') }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-3">
    <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.orderedlist.beatpath_why_heading') }}</p>
    @if ($winners === [])
        <p class="text-[13px] text-muted leading-relaxed">{{ __('components.orderedlist.beatpath_no_winners') }}</p>
    @else
        <ol class="flex flex-col gap-2 text-[13px] leading-relaxed m-0 p-0 list-none" style="color: var(--color-ink)">
            @foreach ($winners as $entry)
                <li style="overflow-wrap:anywhere">
                    {{ __('components.orderedlist.beatpath_why', [
                        'winner' => $entry['winner'],
                        'loser' => $entry['loser'],
                        'strength' => $entry['strength'],
                        'path' => implode(' → ', $entry['path']),
                    ]) }}
                </li>
            @endforeach
        </ol>
    @endif
</div>
