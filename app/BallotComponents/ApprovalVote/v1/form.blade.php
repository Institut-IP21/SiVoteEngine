@foreach ($component->options as $option)
    <label class="checkbox flex -ml-2 p-2 mb-2 cursor-pointer hover:bg-blue-100 hover:bg-opacity-25"
        for="{{ $component->id }}--{{ $loop->iteration }}">
        <span class="checkbox__input">
            <input type="checkbox" id="{{ $component->id }}--{{ $loop->iteration }}" name="{{ $component->id }}[]"
                value="{{ $option }}" />
            <span class="checkbox__control mr-3"></span>
        </span>
        <span class="checkbox__label">{{ $option }}</span>
    </label>
@endforeach
