@foreach ($component->options as $option)
    <label class="radio flex -ml-2 p-2 mb-2 cursor-pointer hover:bg-blue-100 hover:bg-opacity-25"
        for="{{ $component->id }}--{{ $loop->iteration }}">
        <span class="radio__input">
            <input type="radio" name="radio" id="{{ $component->id }}--{{ $loop->iteration }}"
                name="{{ $component->id }}" value="{{ $option }}" />
            <span class="radio__control mr-3"></span>
        </span>
        <span class="radio__label">{{ $option }}</span>
    </label>
@endforeach
@if ($election->abstainable)
    <label class="radio flex -ml-2 p-2 mb-2 cursor-pointer hover:bg-blue-100 hover:bg-opacity-25"
        for="{{ $component->id }}--abstain">
        <span class="radio__input">
            <input type="radio" name="radio" id="{{ $component->id }}--abstain" name="{{ $component->id }}"
                value="abstain" />
            <span class="radio__control mr-3"></span>
        </span>
        <span class="radio__label">Abstain</span>
    </label>
@endif
