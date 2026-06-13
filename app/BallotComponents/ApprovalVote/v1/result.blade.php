@php
$result = $results[$component->id]['results'];
$voters = $result['voters'];
$quorumMet = $quorumMet ?? true;
@endphp

<x-ballot-results-table :shareLabel="__('components.approval.rate')">

    @foreach ($result['state'] as $option => $votes)
    <div
        class="flex flex-row {{ $quorumMet && count($result['winners']) > 1 && in_array($option, $result['winners']) ? 'bg-yellow-100' : '' }}{{ $quorumMet && in_array($option, $result['winners']) ? ' winner bg-green-200' : '' }}">
        <x-ballot-results-table-row>
            {{ $option }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{ $votes }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{-- D2: per-voter approval rate (approvals ÷ participating voters); rows may sum past 100%. --}}
            {{ $voters > 0 ? round(($votes / $voters) * 100, 2) : 0 }}
        </x-ballot-results-table-row>
    </div>
    @endforeach

</x-ballot-results-table>

<div class="mt-4 text-sm">
    <div>{{ __('components.fptp.abstain') }}: {{ $result['abstentions'] }}</div>
    @if ($result['invalid'] > 0)
    <div>{{ $result['invalid'] }} invalid</div>
    @endif
</div>

@if ($quorumMet && $result['winner'] === 'tie')
<div class="p-4 text-center block mt-6 bg-yellow-200">
    {{ __('components.fptp.tie') }} {{ implode(', ', $result['winners']) }}
</div>
@endif
