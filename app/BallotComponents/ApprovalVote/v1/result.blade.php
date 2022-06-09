<div class="row">
    <div class="flex-1 border p-3 font-bold">{{ __('components.option') }}</div>
    <div class="flex-1 border p-3 font-bold">{{ __('components.votes') }}</div>
    <div class="flex-1 border p-3 font-bold">{{ __('components.approval.oftotal') }}</div>
</div>
@php
$result = $results[$component->id]['results'];
@endphp
@foreach ($result['state'] as $option => $votes)
    <div
        class="row {{ count($result['winners']) > 1 && in_array($option, $result['winners']) ? 'bg-yellow-100' : '' }}{{ in_array($option, $result['winners']) ? 'winner bg-green-200' : '' }}">
        <div class="flex-1 border p-3">{{ $option }}</div>
        <div class="flex-1 border p-3">{{ $votes }}</div>
        <div class="flex-1 border p-3">
            {{ round(($votes / $result['total_votes']) * 100, 2) }}
        </div>
    </div>
@endforeach
@if ($result['winner'] === 'tie')
    <div class="p-4 text-center block mt-6 bg-yellow-200">
        {{ __('components.fptp.tie') }} {{ implode(', ', $result['winners']) }}
    </div>
@endif
