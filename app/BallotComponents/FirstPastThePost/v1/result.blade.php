@php
$result = $results[$component->id]['results'];
$validVotes = $result['valid_votes'];
$quorumMet = $quorumMet ?? true;
@endphp

<x-ballot-results-table :shareLabel="__('components.share_valid')">
    @foreach ($result['state'] as $option => $votes)
    <div
        class="flex flex-row {{ $quorumMet && count($result['winners']) > 1 && in_array($option, $result['winners']) ? 'bg-warn-soft' : '' }}{{ $quorumMet && count($result['winners']) === 1 && in_array($option, $result['winners']) ? 'winner bg-secure-soft' : '' }}">
        <x-ballot-results-table-row>
            {{ $option }}
        </x-ballot-results-table-row>

        <x-ballot-results-table-row>
            {{ $votes }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{ $validVotes > 0 ? round(($votes / $validVotes) * 100, 2) : 0 }}
        </x-ballot-results-table-row>
    </div>
    @endforeach
</x-ballot-results-table>

@if ($result['abstentions'] > 0)
<div class="flex flex-row mt-2 text-muted">
    <x-ballot-results-table-row>
        {{ __('components.fptp.abstain') }}
    </x-ballot-results-table-row>
    <x-ballot-results-table-row>
        {{ $result['abstentions'] }}
    </x-ballot-results-table-row>
</div>
@endif

@if ($result['invalid'] > 0)
<div class="flex flex-row text-muted">
    <x-ballot-results-table-row>
        {{ __('components.fptp.invalid') }}
    </x-ballot-results-table-row>
    <x-ballot-results-table-row>
        {{ $result['invalid'] }}
    </x-ballot-results-table-row>
</div>
@endif

@if ($quorumMet && $result['winner'] === 'tie')
<div class="p-4 text-center mt-6 rounded-xl font-semibold bg-warn-soft text-warn-fg">
    {{ __('components.fptp.tie') }} {{ implode(', ', $result['winners']) }}
</div>
@endif
