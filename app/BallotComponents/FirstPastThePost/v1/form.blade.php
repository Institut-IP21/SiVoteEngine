<p class="mb-3.5 text-[13px] text-muted">{{ __('components.fptp.hint') }}</p>
@foreach ($component->options as $option)
    <label class="opt-row" for="{{ $component->id }}--{{ $loop->iteration }}">
        <span class="opt-ctrl opt-ctrl--radio" aria-hidden="true"></span>
        <input type="radio" id="{{ $component->id }}--{{ $loop->iteration }}" name="{{ $component->id }}"
            value="{{ $option }}" @required(! $election->abstainable) />
        <span class="opt-row__label">{{ $option }}</span>
    </label>
@endforeach
@if ($election->abstainable)
    <label class="opt-row" for="{{ $component->id }}--abstain">
        <span class="opt-ctrl opt-ctrl--radio" aria-hidden="true"></span>
        <input type="radio" id="{{ $component->id }}--abstain" name="{{ $component->id }}" value="abstain" checked />
        <span class="opt-row__label">{{ __('Abstain') }}</span>
    </label>
@endif
