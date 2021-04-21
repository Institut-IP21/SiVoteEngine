<div class="row">
    <div class="flex-1 border p-3 font-bold">Option</div>
    <div class="flex-1 border p-3 font-bold">Votes</div>
    <div class="flex-1 border p-3 font-bold">% approval</div>
</div>
@foreach ($results[$component->id]['results'] as $option => $votes)
    <div class="row">
        <div class="flex-1 border p-3">{{ $option }}</div>
        <div class="flex-1 border p-3">{{ $votes }}</div>
        <div class="flex-1 border p-3">{{ ($votes / count($results[$component->id]['results'])) * 100 }}</div>
    </div>
@endforeach
