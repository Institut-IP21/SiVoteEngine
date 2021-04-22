<div class="row">
    <div class="flex-1 border p-3 font-bold">{{ __('components.option') }}</div>
    <div class="flex-1 border p-3 font-bold">{{ __('components.votes') }}</div>
    <div class="flex-1 border p-3 font-bold">{{ __('components.approval.oftotal') }}</div>
</div>
@foreach ($results[$component->id]['results'] as $option => $votes)
    <div class="row">
        <div class="flex-1 border p-3">{{ $option }}</div>
        <div class="flex-1 border p-3">{{ $votes }}</div>
        <div class="flex-1 border p-3">{{ ($votes / count($results[$component->id]['results'])) * 100 }}</div>
    </div>
@endforeach
