@php
$result = $results[$component->id]['results'];
$validVotes = $result['valid_votes'];
$quorumMet = $quorumMet ?? true;
$winners = $result['winners'];
$decided = $quorumMet && count($winners) === 1 && $result['winner'] !== 'tie';
$tied = $quorumMet && count($winners) > 1;

$rows = [];
foreach ($result['state'] as $option => $votes) {
    $isWin = in_array($option, $winners, true);
    $rows[] = [
        'label' => $option,
        'votes' => $votes,
        'pct' => $validVotes > 0 ? $votes / $validVotes * 100 : 0,
        'state' => $isWin ? ($decided ? 'winner' : ($tied ? 'tied' : 'normal')) : 'normal',
    ];
}
@endphp

<x-ballot-result-bars :rows="$rows" :shareLabel="__('components.share_valid')" />

@if ($result['abstentions'] > 0 || $result['invalid'] > 0)
<div class="mt-3 flex flex-col gap-0.5 text-[13px] text-muted">
    @if ($result['abstentions'] > 0)
    <div class="flex justify-between gap-3"><span>{{ __('components.fptp.abstain') }}</span><span>{{ $result['abstentions'] }}</span></div>
    @endif
    @if ($result['invalid'] > 0)
    <div class="flex justify-between gap-3"><span>{{ __('components.fptp.invalid') }}</span><span>{{ $result['invalid'] }}</span></div>
    @endif
</div>
@endif

@if ($quorumMet && $result['winner'] === 'tie')
<div class="p-4 text-center mt-6 rounded-xl font-semibold bg-warn-soft text-warn-fg">
    {{ __('components.fptp.tie') }} {{ implode(', ', $result['winners']) }}
</div>
@endif
