@php
    // Result-first display, consistent with the other question types: the final-round
    // standing as shared bars + a verdict banner; the auditor-grade tabulation is tucked
    // behind a "How the count worked" toggle, collapsed by default.
    $quorumMet = $quorumMet ?? true;
    $res = $results[$component->id]['results'];
    $rounds = $res['rounds'] ?? [];
    $conclusive = (bool) ($res['result']['conclussive'] ?? false);
    $winnerName = $res['result']['conclussive_winner'] ?? null;
    $tied = array_map('strval', $res['result']['winners'] ?? []);

    // Final standing = the LAST round's per-option tallies (audit/meta keys stripped),
    // ordered high→low. Shares are of the continuing ballots in that final round.
    $meta = ['continuing', 'exhausted', 'exhausted_running', 'eliminated', 'eliminated_previously', 'winner', 'tied', 'decision'];
    $final = $rounds === [] ? [] : end($rounds);
    $standing = array_filter($final, fn ($v, $k) => is_int($v) && !in_array($k, $meta, true), ARRAY_FILTER_USE_BOTH);
    arsort($standing);
    $continuing = is_int($final['continuing'] ?? null) ? $final['continuing'] : array_sum($standing);
    $denom = $continuing > 0 ? $continuing : 1;
    $roundCount = count($rounds);
    $leadPct = (int) round((($standing ? reset($standing) : 0)) / $denom * 100);
    $leaderName = $standing !== [] ? array_key_first($standing) : null;

    $rows = [];
    foreach ($standing as $name => $votes) {
        $state = 'normal';
        if ($quorumMet && $conclusive && (string) $name === (string) $winnerName) {
            $state = 'winner';
        } elseif ($quorumMet && !$conclusive && in_array((string) $name, $tied, true)) {
            $state = 'tied';
        }
        $rows[] = ['label' => $name, 'votes' => $votes, 'pct' => $votes / $denom * 100, 'state' => $state];
    }

    // Audit cross-checks (see the disclosure below).
    $accounting = is_array($res['accounting'] ?? null) ? $res['accounting'] : [];
    $preferences = is_array($res['preferences'] ?? null) ? $res['preferences'] : [];
@endphp
<div x-data="{ open: false }">
    {{-- Verdict first: the outcome, then the final-round standing as its evidence. --}}
    @if (! $quorumMet)
    <x-ballot-component.not-binding>
        @if ($leaderName !== null)
            {{ __('components.rankedchoice.outcome_not_binding', ['name' => $leaderName, 'pct' => $leadPct]) }}
        @else
            {{ __('components.not_binding') }}
        @endif
    </x-ballot-component.not-binding>
    @elseif ($conclusive && $winnerName !== null)
    <div class="p-4 text-center mb-4 rounded-xl font-semibold bg-secure-soft text-secure">
        {{ __('components.rankedchoice.winner_headline', ['name' => $winnerName]) }}
        <div class="mt-1 text-sm font-normal text-ink">
            @if ($roundCount <= 1)
                {{ __('components.rankedchoice.outcome_majority', ['pct' => $leadPct]) }}
            @else
                {{ __('components.rankedchoice.outcome_after_rounds', ['rounds' => $roundCount, 'name' => $winnerName]) }}
            @endif
        </div>
    </div>
    @else
    <div class="p-4 text-center mb-4 rounded-xl font-semibold bg-warn-soft text-warn-fg">
        {{ __('components.rankedchoice.no_winner_headline') }}
        <div class="mt-1 text-sm font-normal text-ink">
            @if (count($tied) > 0)
                {{ __('components.rankedchoice.outcome_tie', ['names' => implode(', ', $tied), 'pct' => $leadPct]) }}
            @else
                {{ __('components.rankedchoice.no_majority') }}
            @endif
        </div>
    </div>
    @endif

    {{-- Final standing — same bar component the other question types use. --}}
    <x-ballot-result-bars :rows="$rows" :shareLabel="__('components.rankedchoice.standing_note', ['continuing' => $continuing])" />

    {{-- Progressive disclosure: the auditor-grade tabulation, collapsed by default. --}}
    @if ($roundCount > 0)
    <div class="mt-4 border-t border-line pt-3">
        <button type="button"
            class="flex w-full items-center justify-between gap-2 text-left text-sm font-semibold text-brand-dark hover:brightness-95"
            x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'">
            <span>{{ __('components.rankedchoice.how_counted') }}</span>
            <svg class="w-4 h-4 flex-shrink-0" style="transition: transform .15s ease" x-bind:style="open ? 'transform: rotate(180deg)' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
        </button>
        <div class="mt-3 flex flex-col gap-5" x-show="open" style="display: none;">
            <p class="text-[13px] text-muted leading-relaxed">{{ __('components.rankedchoice.how_counted_hint') }}</p>

            {{-- Full tabulation matrix — votes flow left→right; scrolls horizontally when wide. --}}
            <div class="min-w-0">
                <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.rankedchoice.full_tabulation') }}</p>
                @include($component->component_path . '/tabulation', ['rounds' => $rounds, 'component' => $component])

                {{-- Plain-text log of every elimination, look-back and exhausted movement. --}}
                <p class="mt-4 mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.rankedchoice.count_log_heading') }}</p>
                @include($component->component_path . '/count_log', ['rounds' => $rounds, 'component' => $component])
            </div>

            {{-- First-preference position matrix (independent cross-check on round 1). --}}
            @if ($preferences !== [])
            <div class="min-w-0">
                <p class="mb-1 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.rankedchoice.first_preferences') }}</p>
                <p class="mb-2 text-[12px] text-muted leading-relaxed">{{ __('components.rankedchoice.first_preferences_hint') }}</p>
                @include($component->component_path . '/preferences', ['preferences' => $preferences, 'component' => $component])
            </div>
            @endif

            {{-- Ballot accounting — so the totals visibly reconcile. --}}
            @if ($accounting !== [])
            <div>
                <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.rankedchoice.accounting') }}</p>
                <dl class="text-sm" style="border:1px solid var(--color-line); border-radius:.75rem">
                    <div class="flex justify-between gap-3 px-3 py-2" style="border-bottom:1px solid var(--color-line)">
                        <dt class="text-muted">{{ __('components.rankedchoice.acc_cast') }}</dt>
                        <dd class="font-semibold text-ink">{{ $accounting['cast'] ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2" style="border-bottom:1px solid var(--color-line)">
                        <dt class="text-muted">{{ __('components.rankedchoice.acc_counted') }}</dt>
                        <dd class="font-semibold text-ink">{{ $accounting['counted'] ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2" style="border-bottom:1px solid var(--color-line)">
                        <dt class="text-muted">{{ __('components.rankedchoice.acc_blank') }}</dt>
                        <dd class="font-semibold text-ink">{{ $accounting['blank'] ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-muted">{{ __('components.rankedchoice.acc_invalid') }}</dt>
                        <dd class="font-semibold text-ink">{{ $accounting['invalid_only'] ?? 0 }}</dd>
                    </div>
                </dl>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
