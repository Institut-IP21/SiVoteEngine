@php
$result = $results[$component->id]['results'];
@endphp

<x-ballot-results-table>
    @foreach ($result['state'] as $option => $votes)
    <div
        class="flex flex-row {{ count($result['winners']) > 1 && in_array($option, $result['winners']) ? 'bg-yellow-100' : '' }}{{ in_array($option, $result['winners']) ? 'winner bg-green-200' : '' }}">
        <x-ballot-results-table-row>
            {{ __("components.yesno.$option") }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{ $votes }}
        </x-ballot-results-table-row>
        <x-ballot-results-table-row>
            {{ round(($votes / $result['total_votes']) * 100, 2) }}
        </x-ballot-results-table-row>
    </div>
    </div>
    @endforeach
</x-ballot-results-table>

@if ($result['winner'] === 'tie')
<div class="p-4 text-center block mt-6 bg-yellow-200">
    {{ __('components.yesno.tie') }}
</div>
@endif