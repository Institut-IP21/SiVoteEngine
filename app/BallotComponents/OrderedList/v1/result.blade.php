@php
    // Result-first display, consistent with the other question types: the
    // ordered list up front (plus the optional quota comparison), the
    // Schulze beatpath auditor detail tucked behind a "How the order was
    // decided" toggle, collapsed by default.
    $quorumMet = $quorumMet ?? true;
    $res = $results[$component->id]['results'];
    $seats = $res['seats'];
    $ranking = $res['ranking'];
    $hasResult = $ranking !== [];
    $bands = $res['bands'];
    $cutoffDecision = $res['cutoff_decision'];
    $beatpath = $res['beatpath'];
    $pairwise = $res['pairwise'];
    $accounting = $res['accounting'];
    $corrected = $res['corrected'];

    // Everything below reads the engine's OFFICIAL outcome (the binding
    // quota slate when official, else the votes-alone slate) -- never a
    // re-derivation from bands/quota state here (D15).
    $officialOrder = $res['official_order'];
    $officialPositions = $res['official_positions'];
    $officialContested = $res['official_contested'];
    $success = $hasResult && $res['final'];

    // When not final, two INDEPENDENT figures: membership (seats with no
    // certain occupant yet) and ordering (surely-seated candidates whose
    // exact seat is still tied).
    $membershipContested = $officialContested !== [] ? max(1, min($seats, count($ranking)) - count($officialOrder)) : null;
    $orderTiesCount = count($officialOrder) - count($officialPositions);
    $quotaPending = $res['official'] === 'corrected' && ($corrected['provisional'] ?? false);
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
            {{ trans_choice('components.orderedlist.elected_headline', $seats, ['seats' => $seats]) }}
        </div>
    @else
        <div class="p-4 text-center mb-4 rounded-xl font-semibold bg-warn-soft text-warn-fg">
            @if ($membershipContested !== null)
                <p class="m-0">{{ trans_choice('components.orderedlist.contested_headline', $membershipContested, ['count' => $membershipContested]) }}</p>
            @endif
            @if ($orderTiesCount > 0)
                <p class="m-0">{{ trans_choice('components.orderedlist.order_ties_note', $orderTiesCount, ['count' => $orderTiesCount]) }}</p>
            @endif
            @if ($quotaPending)
                <p class="m-0">{{ __('components.orderedlist.quota_pending_note') }}</p>
            @endif
        </div>
    @endif

    @if (! empty($res['warnings']))
    <div class="mb-4 text-[12px] text-muted">
        <p class="mb-1 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.result.notes_label') }}</p>
        <ul class="list-disc ml-4 space-y-0.5">
            @foreach ($res['warnings'] as $warning)
            <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if ($hasResult)
        @include($component->component_path . '/_ranking', ['res' => $res, 'component' => $component])

        @if ($res['corrected'] !== null)
            @include($component->component_path . '/_quota', ['res' => $res, 'component' => $component])
        @endif
    @endif

    {{-- Progressive disclosure: the Schulze beatpath auditor detail, collapsed by default. --}}
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

                <div class="min-w-0">
                    <p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.orderedlist.beatpath_heading') }}</p>
                    <p class="mb-2 text-[12px] text-muted leading-relaxed">{{ __('components.orderedlist.beatpath_hint') }}</p>
                    @include($component->component_path . '/_beatpath', ['beatpath' => $beatpath, 'candidates' => $pairwise['candidates']])
                </div>
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
