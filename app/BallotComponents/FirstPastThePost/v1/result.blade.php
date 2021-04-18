@foreach ($results[$component->id]['results'] as $value => $votes)
    <div class="row">{{ $value }} - {{ $votes }}</div>
@endforeach
