@php
$result = $results[$component->id]['results'];
$validVotes = $result['valid_votes'];
$quorumMet = $quorumMet ?? true;

$rows = [];
foreach ($result['state'] as $option => $votes) {
    $rows[] = [
        'label' => __("components.yesno.$option"),
        'votes' => $votes,
        'pct' => $validVotes > 0 ? $votes / $validVotes * 100 : 0,
        'state' => ($quorumMet && $result['passed'] && in_array($option, $result['winners'], true)) ? 'winner' : 'normal',
    ];
}
@endphp

<x-ballot-result-bars :rows="$rows" :shareLabel="__('components.share_valid')" />

@if ($result['abstentions'] > 0 || $result['invalid'] > 0)
<div class="mt-3 flex flex-col gap-0.5 text-[13px] text-muted">
    @if ($result['abstentions'] > 0)
    <div class="flex justify-between gap-3"><span>{{ __('components.yesno.abstain') }}</span><span>{{ $result['abstentions'] }}</span></div>
    @endif
    @if ($result['invalid'] > 0)
    <div class="flex justify-between gap-3"><span>{{ __('components.yesno.invalid') }}</span><span>{{ $result['invalid'] }}</span></div>
    @endif
</div>
@endif

@if ($quorumMet)
@if ($result['passed'])
<div class="p-4 text-center mt-6 rounded-xl font-semibold bg-secure-soft text-secure">
    {{ __('components.yesno.carried') }}
</div>
@elseif ($result['winner'] === 'tie')
<div class="p-4 text-center mt-6 rounded-xl font-semibold bg-warn-soft text-warn-fg">
    {{ __('components.yesno.not_carried_tied', ['yes' => $result['state']['yes'] ?? 0, 'no' => $result['state']['no'] ?? 0]) }}
</div>
@else
<div class="p-4 text-center mt-6 rounded-xl font-semibold bg-warn-soft text-warn-fg">
    {{ __('components.yesno.not_carried') }}
</div>
@endif
@endif
