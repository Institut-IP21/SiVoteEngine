@php
    // Result-first display, consistent with the other question types: the
    // ordered list up front (plus the optional quota comparison), the
    // Ranked-Pairs auditor detail tucked behind a "How the order was
    // decided" toggle, collapsed by default.
    $quorumMet = $quorumMet ?? true;
    $res = $results[$component->id]['results'];
    $seats = $res['seats'];
    $ranking = $res['ranking'];
    $hasResult = $ranking !== [];
    $bands = $res['bands'];
    $cutoffDecision = $res['cutoff_decision'];
    $final = $res['final'];
    $lockInLog = $res['lock_in_log'];
    $pairwise = $res['pairwise'];
    $accounting = $res['accounting'];

    // Success = there is no unresolved tie left anywhere in the list. A band
    // that straddles the seat cutoff is still just one of $bands (it's the
    // same PositionResolver band, only flagged affects_cutoff), so "every
    // band is resolved by the runner" already covers the cutoff case too —
    // once the runner settles it, the result reads as a firm election, not
    // a permanently-contested one.
    $success = $hasResult && ($bands === [] || ($final !== null && $final['complete']));

    // "M seats contested": when the cutoff itself is undecided, M is exactly
    // the DTO's own remaining_seats (how many of the K seats have no settled
    // occupant yet). Otherwise membership is settled and the only open
    // question is the final ORDER among some already-elected/excluded
    // candidates — M there is how many of their ranking slots fall within
    // the top K (an unresolved tie's own span can overshoot K when it
    // straddles the boundary in the general case, but affects_cutoff already
    // routes that case through cutoffDecision above).
    $contestedCount = 0;
    if ($hasResult && ! $success) {
        if ($cutoffDecision !== null) {
            $contestedCount = $cutoffDecision['remaining_seats'];
        } else {
            $candidateIndex = [];
            foreach ($ranking as $idx => $entry) {
                $candidateIndex[$entry['candidate']] = $idx;
            }

            $contested = [];
            foreach ($bands as $band) {
                $resolvedBand = $final !== null && collect($band['candidates'])->every(
                    fn ($m) => collect($final['order'])->firstWhere('candidate', $m)['tied'] === false
                );
                if ($resolvedBand) {
                    continue;
                }
                foreach ($band['candidates'] as $c) {
                    if (($candidateIndex[$c] ?? PHP_INT_MAX) < $seats) {
                        $contested[$c] = true;
                    }
                }
            }
            $contestedCount = count($contested);
        }
    }
@endphp
<div x-data="{ open: false }">
    @if (! $quorumMet)
        <x-ballot-component.not-binding>
            {{ __('components.orderedlist.outcome_not_binding') }}
        </x-ballot-component.not-binding>
    @elseif (! $hasResult)
        <div class="p-4 text-center mb-4 rounded-xl text-sm text-muted" style="border:1px solid var(--color-line)">
            {{ __('components.orderedlist.no_result_yet') }}
        </div>
    @elseif ($success)
        <div class="p-4 text-center mb-4 rounded-xl font-semibold bg-secure-soft text-secure">
            {{ __('components.orderedlist.elected_headline', ['seats' => $seats]) }}
        </div>
    @else
        <div class="p-4 text-center mb-4 rounded-xl font-semibold bg-warn-soft text-warn-fg">
            {{ __('components.orderedlist.contested_headline', ['count' => $contestedCount]) }}
        </div>
    @endif

    @if ($hasResult)
        @include($component->component_path . '/_ranking', ['res' => $res, 'component' => $component])

        @if ($res['corrected'] !== null)
            @include($component->component_path . '/_quota', ['res' => $res, 'component' => $component])
        @endif
    @endif

    {{-- Progressive disclosure: the Ranked-Pairs auditor detail, collapsed by default. --}}
    <div class="mt-4 border-t border-line pt-3">
        <button type="button"
            class="flex w-full items-center justify-between gap-2 text-left text-sm font-semibold text-brand-dark hover:brightness-95"
            x-on:click="open = !open" x-bind:aria-expanded="open ? 'true' : 'false'">
            <span>{{ __('components.orderedlist.how_decided') }}</span>
            <svg class="w-4 h-4 flex-shrink-0" style="transition: transform .15s ease" x-bind:style="open ? 'transform: rotate(180deg)' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6" /></svg>
        </button>
        <div class="mt-3 flex flex-col gap-5" x-show="open" style="display: none;">
            <p class="text-[13px] text-muted leading-relaxed">{{ __('components.orderedlist.how_decided_hint') }}</p>

            @if ($hasResult)
                <div class="min-w-0">
                    <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.orderedlist.pairwise_heading') }}</p>
                    <p class="mb-2 text-[12px] text-muted leading-relaxed">{{ __('components.orderedlist.pairwise_hint') }}</p>
                    @include($component->component_path . '/_pairwise', ['pairwise' => $pairwise])
                </div>

                @if ($lockInLog !== [])
                    <div class="min-w-0">
                        <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.orderedlist.log_heading') }}</p>
                        @include($component->component_path . '/_lock_in_log', ['log' => $lockInLog])
                    </div>
                @endif
            @endif

            {{-- Ballot accounting — reconciles even when nothing has been counted yet. --}}
            <div>
                <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.orderedlist.accounting') }}</p>
                <dl class="text-sm" style="border:1px solid var(--color-line); border-radius:.75rem">
                    <div class="flex justify-between gap-3 px-3 py-2" style="border-bottom:1px solid var(--color-line)">
                        <dt class="text-muted">{{ __('components.orderedlist.acc_cast') }}</dt>
                        <dd class="font-semibold text-ink">{{ $accounting['cast'] ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2" style="border-bottom:1px solid var(--color-line)">
                        <dt class="text-muted">{{ __('components.orderedlist.acc_counted') }}</dt>
                        <dd class="font-semibold text-ink">{{ $accounting['counted'] ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2" style="border-bottom:1px solid var(--color-line)">
                        <dt class="text-muted">{{ __('components.orderedlist.acc_blank') }}</dt>
                        <dd class="font-semibold text-ink">{{ $accounting['blank'] ?? 0 }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-muted">{{ __('components.orderedlist.acc_invalid') }}</dt>
                        <dd class="font-semibold text-ink">{{ $accounting['invalid_only'] ?? 0 }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
