@php
// Seats-aware headline (D1/D3 top-K), mirroring OrderedList's settled-vs-
// contested pattern: a clean top-K (or the classic single-winner case, at
// seats=1) shows the elected slate up front; a genuine tie at the cutoff is
// reported — never resolved — as a plain contested note naming how many
// seats it competes for. The engine never picks among contested options.
$result = $results[$component->id]['results'];
$voters = $result['voters'];
$quorumMet = $quorumMet ?? true;
$seats = $result['seats'];
$elected = $result['elected'];
$contested = $result['contested'];
$contestedSeats = $result['contested_seats'];
$hasResult = $result['total_approvals'] > 0;
$success = $hasResult && $contested === [];

$rows = [];
foreach ($result['state'] as $option => $votes) {
    $isElected = $quorumMet && in_array($option, $elected, true);
    $isContested = $quorumMet && in_array($option, $contested, true);
    $rows[] = [
        'label' => $option,
        'votes' => $votes,
        // D2: per-voter approval rate (approvals ÷ participating voters).
        'pct' => $voters > 0 ? $votes / $voters * 100 : 0,
        'state' => $isElected ? 'winner' : ($isContested ? 'tied' : 'normal'),
    ];
}
@endphp

@if (! $quorumMet)
<x-ballot-component.not-binding />
@elseif (! $hasResult)
<div class="p-4 text-center mb-4 rounded-xl text-sm text-muted" style="border:1px solid var(--color-line)">
    {{ __('components.approval.no_result_yet') }}
</div>
@elseif ($success)
<div class="p-4 text-center mb-4 rounded-xl font-semibold bg-secure-soft text-secure">
    {{-- seats=1 keeps the original single-winner wording byte-for-byte;
         seats>1 uses the new seats-aware headline (OrderedList's pattern). --}}
    @if ($seats === 1)
        {{ __('components.approval.winner_is', ['name' => $elected[0]]) }}
    @else
        {{ __('components.approval.elected_headline', ['seats' => $seats]) }}
    @endif
</div>
@else
<div class="p-4 text-center mb-4 rounded-xl font-semibold bg-warn-soft text-warn-fg">
    @if ($seats === 1)
        {{ __('components.approval.tie') }} {{ implode(', ', $contested) }}
    @else
        {{ __('components.approval.contested_headline', ['count' => $contestedSeats]) }}
    @endif
</div>
@endif

@if ($quorumMet && $hasResult && $seats > 1)
<p class="mb-2 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.approval.seats_label', ['seats' => $seats]) }}</p>
@endif

@if (! empty($result['warnings']))
<div class="mb-4 text-[12px] text-muted">
    <p class="mb-1 text-[11px] uppercase tracking-[0.07em] font-bold text-muted">{{ __('components.result.notes_label') }}</p>
    <ul class="list-disc ml-4 space-y-0.5">
        @foreach ($result['warnings'] as $warning)
        <li>{{ $warning }}</li>
        @endforeach
    </ul>
</div>
@endif

<x-ballot-result-bars :rows="$rows" :shareLabel="__('components.approval.rate')" />

<div class="mt-3 flex flex-col gap-0.5 text-[13px] text-muted">
    <div class="flex justify-between gap-3"><span>{{ __('components.fptp.abstain') }}</span><span>{{ $result['abstentions'] }}</span></div>
    @if ($result['invalid'] > 0)
    <div class="flex justify-between gap-3"><span>{{ __('components.fptp.invalid') }}</span><span>{{ $result['invalid'] }}</span></div>
    @endif
</div>
