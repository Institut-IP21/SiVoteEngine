@foreach ($component->options as $option)
    <div>
        <input type="radio" id="{{ $component->id }}--{{ $loop->iteration }}" name="{{ $component->id }}"
            value="{{ $option }}">
        <label for="{{ $component->id }}--{{ $loop->iteration }}">{{ $option }}</label>
    </div>
@endforeach
@if ($election->abstainable)
    <div>
        <input type="radio" id="{{ $component->id }}--abstain" name="{{ $component->id }}" value="abstain">
        <label for="{{ $component->id }}--abstain">Abstain</label>
    </div>
@endif
