@php
    // Result-first display (progressive disclosure). The public sees a plain-language
    // outcome + the final standing as bars; the auditor-grade round-by-round runoff is
    // tucked behind a "How the count worked" toggle, collapsed by default in every case.
    $quorumMet = $quorumMet ?? true;
    $res = $results[$component->id]['results'];
    $rounds = $res['rounds'] ?? [];
    $conclusive = (bool) ($res['result']['conclussive'] ?? false);
    $winnerName = $res['result']['conclussive_winner'] ?? null;
    $tied = $res['result']['winners'] ?? [];

    // Final standing = the LAST round's per-option tallies (audit/meta keys stripped),
    // ordered high→low. Shares are of the continuing ballots in that final round.
    $meta = ['continuing', 'exhausted', 'exhausted_running', 'eliminated', 'eliminated_previously', 'winner', 'tied'];
    $final = $rounds === [] ? [] : end($rounds);
    $standing = array_filter($final, fn ($v, $k) => is_int($v) && !in_array($k, $meta, true), ARRAY_FILTER_USE_BOTH);
    arsort($standing);
    $continuing = is_int($final['continuing'] ?? null) ? $final['continuing'] : array_sum($standing);
    $denom = $continuing > 0 ? $continuing : 1;
    $roundCount = count($rounds);

    $leadName = array_key_first($standing);
    $leadVotes = $leadName === null ? 0 : $standing[$leadName];
    $leadPct = (int) round($leadVotes / $denom * 100);
@endphp
<div x-data="{ open: false }">
    {{-- Plain-language outcome — always visible, no rounds/transfers/exhausted jargon. --}}
    @if (! $quorumMet)
        <div class="rounded-xl border border-line bg-canvas p-4">
            <div class="font-semibold text-ink">{{ __('components.rankedchoice.provisional_leader', ['name' => $leadName ?? '—']) }}</div>
            <p class="mt-1 text-sm text-muted">{{ __('components.rankedchoice.outcome_not_binding', ['name' => $leadName ?? '—', 'pct' => $leadPct]) }}</p>
        </div>
    @elseif ($conclusive && $winnerName !== null)
        <div class="rounded-xl p-4" style="background: var(--color-secure-soft); color: var(--color-secure)">
            <div class="flex items-center gap-2 font-bold text-[17px]">
                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5" /></svg>
                <span style="overflow-wrap:anywhere">{{ __('components.rankedchoice.winner_headline', ['name' => $winnerName]) }}</span>
            </div>
            <p class="mt-1.5 text-sm leading-relaxed" style="color: var(--color-ink)">
                @if ($roundCount <= 1)
                    {{ __('components.rankedchoice.outcome_majority', ['pct' => $leadPct]) }}
                @else
                    {{ __('components.rankedchoice.outcome_after_rounds', ['rounds' => $roundCount, 'name' => $winnerName]) }}
                @endif
            </p>
        </div>
    @else
        <div class="rounded-xl p-4" style="background: var(--color-warn-soft); color: var(--color-warn-fg)">
            <div class="font-bold text-[17px]">{{ __('components.rankedchoice.no_winner_headline') }}</div>
            <p class="mt-1.5 text-sm leading-relaxed" style="color: var(--color-ink)">
                @if (count($tied) > 0)
                    {{ __('components.rankedchoice.outcome_tie', ['names' => implode(', ', $tied), 'pct' => $leadPct]) }}
                @else
                    {{ __('components.rankedchoice.no_majority') }}
                @endif
            </p>
        </div>
    @endif

    {{-- Final standing — the last round as plain bars, winner row highlighted. --}}
    @if (count($standing) > 0)
    <div class="mt-4">
        <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.rankedchoice.final_standing') }}</p>
        <div class="flex flex-col gap-2.5">
            @foreach ($standing as $name => $votes)
            @php
                $pct = (int) round($votes / $denom * 100);
                $isWinner = $quorumMet && $conclusive && (string) $name === (string) $winnerName;
            @endphp
            <div>
                <div class="flex items-baseline justify-between gap-3 text-sm">
                    <span class="font-semibold text-ink" style="overflow-wrap:anywhere">{{ $name }}</span>
                    <span class="flex-shrink-0 text-muted"><span class="font-bold text-ink">{{ $pct }}%</span> · {{ $votes }}</span>
                </div>
                <div class="mt-1 h-2.5 w-full rounded-full overflow-hidden" style="background: var(--color-canvas); border: 1px solid var(--color-line)">
                    <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $isWinner ? 'var(--color-secure)' : 'var(--color-brand)' }}"></div>
                </div>
            </div>
            @endforeach
        </div>
        <p class="mt-2 text-[11px] text-muted">{{ __('components.rankedchoice.standing_note', ['continuing' => $continuing]) }}</p>
    </div>
    @endif

    {{-- Progressive disclosure: the auditor-grade round-by-round runoff, collapsed by default. --}}
    @if ($roundCount > 0)
    <div class="mt-4 border-t border-line pt-3">
        <button type="button"
            class="flex w-full items-center justify-between gap-2 text-left text-sm font-semibold text-brand-dark hover:brightness-95"
            x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'">
            <span>{{ __('components.rankedchoice.how_counted') }}</span>
            <svg class="w-4 h-4 flex-shrink-0" style="transition: transform .15s ease" x-bind:style="open ? 'transform: rotate(180deg)' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
        </button>
        <div class="mt-3 flex flex-col gap-3" x-show="open" style="display: none;">
            <p class="text-[13px] text-muted leading-relaxed">{{ __('components.rankedchoice.how_counted_hint') }}</p>
            @foreach ($rounds as $i => $round)
            @include($component->component_path . '/round', ['round' => $round, 'component' => $component, 'i' => $i, 'round_prefix' => ''])
            @endforeach
        </div>
    </div>
    @endif
</div>
