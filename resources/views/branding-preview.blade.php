@extends('layouts.main')

@section('title', __('ballot.single'))

@section('body')
{{-- Faithful branding preview: the REAL voter-facing shell (wrapper / logo / title)
     with the organizer's saved personalization, so the logo + accent colour render
     exactly as voters will see them. A small static sample question (using the same
     surface + option-row classes as the live ballot) stands in for real content — the
     branding-critical chrome is the real shell, not a redraw. --}}
<x-ballot-wrapper :pers="$pers">
    <x-ballot-logo :pers="$pers" />

    <x-ballot-title :ballot="$ballot" />

    <div class="bg-white border border-line rounded-2xl shadow-[0_1px_2px_rgba(16,30,40,.05)] p-5 sm:p-6">
        <div class="flex items-baseline justify-between gap-3">
            <h2 class="font-bold text-base sm:text-lg text-ink leading-snug">{{ $sample->title }}</h2>
            <span class="text-[11px] font-semibold uppercase tracking-[0.05em] text-muted">{{ $sample->type_name }}</span>
        </div>
        <div class="mt-4">
            @foreach ($sample->options as $i => $option)
                <label class="opt-row" for="preview-opt-{{ $i }}">
                    <span class="opt-ctrl opt-ctrl--radio" aria-hidden="true"></span>
                    <input type="radio" id="preview-opt-{{ $i }}" name="preview" value="{{ $option }}" disabled />
                    <span class="opt-row__label">{{ $option }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <button type="button" class="ballot-submit mt-6">{{ __('ballot.submit') }}</button>

    <p class="mt-5 mb-10 text-center text-[11px] leading-relaxed text-muted">
        {{ __('ballot.anonymous') }}
    </p>
</x-ballot-wrapper>
@endsection
