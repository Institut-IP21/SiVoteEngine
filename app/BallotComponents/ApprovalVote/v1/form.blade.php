<p class="mb-3.5 text-[13px] text-muted">{{ __('components.approval.hint') }}</p>
@foreach ($component->options as $option)
    <label class="opt-row" for="{{ $component->id }}--{{ $loop->iteration }}">
        <span class="opt-ctrl opt-ctrl--check" aria-hidden="true"></span>
        <input type="checkbox" id="{{ $component->id }}--{{ $loop->iteration }}" name="{{ $component->id }}[]"
            value="{{ $option }}" />
        <span class="opt-row__label">{{ $option }}</span>
    </label>
@endforeach
