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
    $corrected = $res['corrected'];

    // A binding quota that is still provisional (needs the runner to settle
    // a tie before it can promote) must keep the headline from claiming a
    // final result -- even when the top-K membership/order is otherwise
    // fully settled. An INFEASIBLE binding quota is terminal, not pending:
    // the natural list is genuinely final, so it must not block success.
    $quotaPending = $corrected !== null && ($corrected['binding'] ?? false) && ($corrected['provisional'] ?? false);

    // A band is "resolved" once every one of its members appears in $final
    // with tied === false (guarded: a member missing from $final['order']
    // altogether -- can't happen once $final exists, but never trust an
    // unguarded array access here -- counts as still tied).
    $bandIsResolved = fn (array $band) => $final !== null && collect($band['candidates'])->every(
        fn ($m) => (collect($final['order'])->firstWhere('candidate', $m)['tied'] ?? true) === false
    );

    // The only bands that can still affect the K reported seats are the ones
    // whose span starts at or before the cutoff; a band entirely below K
    // (span[0] > K) is display-only ordering entanglement among
    // already-excluded candidates. Success = a fully-determined election:
    // no such band exists, or every one of them has been resolved by the
    // runner (mirrors RunnerResolutionApplier's own "complete" rule).
    $topKBands = array_values(array_filter($bands, fn ($b) => ($b['span'][0] ?? 1) <= $seats));
    $success = $hasResult && ($topKBands === [] || ($final !== null && ($final['complete'] ?? false))) && ! $quotaPending;

    // When not final, report two INDEPENDENT figures rather than one
    // conflated count: membership (does this seat have a settled occupant at
    // all?) and ordering (the occupants are settled, only their relative
    // order within the top K isn't). A band can only ever contribute to one
    // of the two -- a straddling band (span[1] > seats) is membership-doubt
    // and is exactly what cutoff_decision already summarizes; a band wholly
    // inside the top K (span[1] <= seats) is a pure order-tie among already-
    // elected candidates.
    $membershipContested = $cutoffDecision !== null ? $cutoffDecision['remaining_seats'] : null;
    $orderTiesCount = 0;
    if ($hasResult && ! $success) {
        foreach ($bands as $band) {
            if (($band['span'][1] ?? 0) > $seats) {
                continue;
            }
            if (! $bandIsResolved($band)) {
                $orderTiesCount++;
            }
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
            @if ($membershipContested !== null)
                <p class="m-0">{{ __('components.orderedlist.contested_headline', ['count' => $membershipContested]) }}</p>
            @endif
            @if ($orderTiesCount > 0)
                <p class="m-0">{{ __('components.orderedlist.order_ties_note', ['count' => $orderTiesCount]) }}</p>
            @endif
            @if ($quotaPending)
                <p class="m-0">{{ __('components.orderedlist.quota_pending_note') }}</p>
            @endif
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
