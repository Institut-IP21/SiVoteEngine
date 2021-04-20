@foreach ($results[$component->id]['results'] as $value => $votes)
    <div class="row">{{ __($value) }} - {{ $votes }}</div>
@endforeach
