@php
$result = $results[$component->id]['results'];
$voters = $result['voters'];
$quorumMet = $quorumMet ?? true;
$winners = $result['winners'];
$multi = count($winners) > 1;
$decided = $quorumMet && count($winners) === 1 && $result['winner'] !== 'tie';

$rows = [];
foreach ($result['state'] as $option => $votes) {
    $isWin = $quorumMet && in_array($option, $winners, true);
    $rows[] = [
        'label' => $option,
        'votes' => $votes,
        // D2: per-voter approval rate (approvals ÷ participating voters).
        'pct' => $voters > 0 ? $votes / $voters * 100 : 0,
        'state' => $isWin ? ($multi ? 'tied' : 'winner') : 'normal',
    ];
}
@endphp

@if (! $quorumMet)
<x-ballot-component.not-binding />
@elseif ($decided)
<div class="p-4 text-center mb-4 rounded-xl font-semibold bg-secure-soft text-secure">
    {{ __('components.approval.winner_is', ['name' => $result['winner']]) }}
</div>
@elseif ($result['winner'] === 'tie')
<div class="p-4 text-center mb-4 rounded-xl font-semibold bg-warn-soft text-warn-fg">
    {{ __('components.approval.tie') }} {{ implode(', ', $result['winners']) }}
</div>
@endif

<x-ballot-result-bars :rows="$rows" :shareLabel="__('components.approval.rate')" />

<div class="mt-3 flex flex-col gap-0.5 text-[13px] text-muted">
    <div class="flex justify-between gap-3"><span>{{ __('components.fptp.abstain') }}</span><span>{{ $result['abstentions'] }}</span></div>
    @if ($result['invalid'] > 0)
    <div class="flex justify-between gap-3"><span>{{ __('components.fptp.invalid') }}</span><span>{{ $result['invalid'] }}</span></div>
    @endif
</div>
