<div class="row">
    <div class="flex-1 border p-3 font-bold">{{ __('components.option') }}</div>
    <div class="flex-1 border p-3 font-bold">{{ __('components.votes') }}</div>
    <div class="flex-1 border p-3 font-bold">{{ __('components.oftotal') }}</div>
</div>
@foreach ($results[$component->id]['results'] as $option => $votes)
    <div class="row">
        <div class="flex-1 border p-3">{{ __("components.yesno.$option") }}</div>
        <div class="flex-1 border p-3">{{ $votes }}</div>
        <div class="flex-1 border p-3">{{ round(($votes / array_sum($results[$component->id]['results'])) * 100, 2) }}
        </div>
    </div>
@endforeach
