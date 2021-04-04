@foreach ($results[$component->id] as $value => $votes)
    {{ $value }} - {{ $votes }}
@endforeach
