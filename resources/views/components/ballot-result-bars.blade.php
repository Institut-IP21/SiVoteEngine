@props(['rows' => [], 'shareLabel' => null])
{{-- Polished results display shared by YesNo / FPTP / ApprovalVote: one horizontal
     bar per option (share-of-valid drives the width), winner row tinted + ticked,
     tied rows in warn. Mirrors the ranked-choice "Final standing" so every question
     type reads the same. Each $row: ['label','votes','pct','state' => winner|tied|normal]. --}}
<div class="flex flex-col gap-1.5">
    @foreach ($rows as $row)
    @php
        $state = $row['state'] ?? 'normal';
        $pct = (float) ($row['pct'] ?? 0);
        $isWinner = $state === 'winner';
        $isTied = $state === 'tied';
        // Loser/normal rows are neutral grey (#c2ced4, matching the in-app track) — NOT
        // --color-brand: the brand colour signals "live/interactive", never a defeated option.
        $fill = $isWinner ? 'var(--color-secure)' : ($isTied ? 'var(--color-warn)' : '#c2ced4');
        // Trim trailing zeros: 66.7, 50, 100 — clean but precise enough; the raw count sits beside it.
        $pctLabel = rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.');
    @endphp
    <div @class([
        'rounded-lg px-2.5 py-1.5 -mx-2.5',
        'winner bg-secure-soft' => $isWinner,
        'bg-warn-soft' => $isTied,
    ])>
        <div class="flex items-baseline justify-between gap-3 text-sm">
            <span class="text-ink {{ $isWinner ? 'font-bold' : 'font-medium' }}" style="overflow-wrap:anywhere">
                {{ $row['label'] }}@if ($isWinner) <span style="color:var(--color-secure)" aria-hidden="true">✓</span>@endif
            </span>
            <span class="flex-shrink-0 text-muted"><span class="font-bold text-ink">{{ $pctLabel }}%</span> · {{ $row['votes'] }}</span>
        </div>
        <div class="mt-1 h-2.5 w-full rounded-full overflow-hidden" style="background: var(--color-canvas); border: 1px solid var(--color-line)">
            <div class="h-full rounded-full" style="width: {{ max(0, min(100, $pct)) }}%; background: {{ $fill }}"></div>
        </div>
    </div>
    @endforeach

    @if ($shareLabel)
    <p class="mt-1 text-[11px] text-muted">{{ $shareLabel }}</p>
    @endif
</div>
