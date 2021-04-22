@foreach ($component->options as $option)
    <label class="radio flex -ml-2 p-2 mb-2 cursor-pointer hover:bg-blue-100 hover:bg-opacity-25"
        for="{{ $component->id }}--{{ $loop->iteration }}">
        <span class="radio__input">
            <input type="radio" id="{{ $component->id }}--{{ $loop->iteration }}" name="{{ $component->id }}"
                value="{{ $option }}" required="{{ $election->abstainable }}" />
            <span class="radio__control mr-3"></span>
        </span>
        <span class="radio__label">{{ __($option) }}</span>
    </label>
@endforeach
@if ($election->abstainable)
    <label class="radio flex -ml-2 p-2 mb-2 cursor-pointer hover:bg-blue-100 hover:bg-opacity-25"
        for="{{ $component->id }}--abstain">
        <span class="radio__input">
            <input type="radio" id="{{ $component->id }}--abstain" name="{{ $component->id }}" value="abstain"
                checked />
            <span class="radio__control mr-3"></span>
        </span>
        <span class="radio__label">{{ __('Abstain') }}</span>
    </label>
@endif
