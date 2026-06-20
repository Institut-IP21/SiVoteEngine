@props(['component', 'election', 'type' => 'radio', 'localize' => false])
{{-- Shared option rows for the single-choice (radio) and approval (checkbox) ballots,
     plus the abstain row for radio types when the election allows abstaining. Keeps the
     exact submission contract: radio name="{id}", checkbox name="{id}[]". The .opt-row__
     label wrap is handled in app.scss. --}}
@php
    $ctrlClass = $type === 'checkbox' ? 'opt-ctrl--check' : 'opt-ctrl--radio';
    $fieldName = $type === 'checkbox' ? $component->id . '[]' : $component->id;
@endphp
@foreach ($component->options as $option)
    <label class="opt-row" for="{{ $component->id }}--{{ $loop->iteration }}">
        <span class="opt-ctrl {{ $ctrlClass }}" aria-hidden="true"></span>
        <input type="{{ $type }}" id="{{ $component->id }}--{{ $loop->iteration }}" name="{{ $fieldName }}"
            value="{{ $option }}" @if ($type === 'radio') @required(! $election->abstainable) @endif />
        <span class="opt-row__label">{{ $localize ? __($option) : $option }}</span>
    </label>
@endforeach
@if ($type === 'radio' && $election->abstainable)
    <label class="opt-row" for="{{ $component->id }}--abstain">
        <span class="opt-ctrl opt-ctrl--radio" aria-hidden="true"></span>
        <input type="radio" id="{{ $component->id }}--abstain" name="{{ $component->id }}" value="abstain" checked />
        <span class="opt-row__label">{{ __('Abstain') }}</span>
    </label>
@endif
