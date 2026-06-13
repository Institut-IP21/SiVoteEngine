@php
$result = $results[$component->id]['results'];
$validVotes = $result['valid_votes'];
@endphp

<x-ballot-results-table :shareLabel="__('components.share_valid')">
    @foreach ($result['state'] as $option => $votes)
    <div
        class="flex flex-row {{ $result['passed'] && in_array($option, $result['winners']) ? 'winner bg-green-200' : '' }}">
        <x-ballot-results-table-row>
            {{ __("components.yesno.$option") }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{ $votes }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{ $validVotes > 0 ? round(($votes / $validVotes) * 100, 2) : '0.00' }}
        </x-ballot-results-table-row>
    </div>
    @endforeach

    @if ($result['abstentions'] > 0)
    <div class="flex flex-row text-gray-500">
        <x-ballot-results-table-row>
            {{ __('components.yesno.abstain') }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{ $result['abstentions'] }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>—</x-ballot-results-table-row>
    </div>
    @endif

    @if ($result['invalid'] > 0)
    <div class="flex flex-row text-gray-500">
        <x-ballot-results-table-row>
            {{ __('components.yesno.invalid') }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{ $result['invalid'] }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>—</x-ballot-results-table-row>
    </div>
    @endif
</x-ballot-results-table>

@if ($result['passed'])
<div class="p-4 text-center block mt-6 bg-green-200">
    {{ __('components.yesno.carried') }}
</div>
@elseif ($result['winner'] === 'tie')
<div class="p-4 text-center block mt-6 bg-yellow-200">
    {{ __('components.yesno.not_carried_tied', ['yes' => $result['state']['yes'] ?? 0, 'no' => $result['state']['no'] ?? 0]) }}
</div>
@else
<div class="p-4 text-center block mt-6 bg-yellow-200">
    {{ __('components.yesno.not_carried') }}
</div>
@endif
