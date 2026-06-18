@extends('layouts.main')

@section('title', __('ballot.single') . ' - eGlasovanje.si')

@section('body')
<x-ballot-wrapper :pers="$pers">
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-[#f0d9a8] bg-warn-soft px-4 py-3 text-sm text-[#8a5a12]">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.42 0Z" />
        </svg>
        <span>{{ __('ballot.preview.warning') }}</span>
    </div>

    <x-ballot-logo :pers="$pers" />

    <x-ballot-title :ballot="$ballot" />

    @if ($ballot->components)
        <div class="flex flex-col gap-4">
            @foreach ($ballot->components as $component)
                <div class="bg-white border border-line rounded-2xl shadow-[0_1px_2px_rgba(16,30,40,.05)] p-5 sm:p-6">
                    <x-ballot-component.title :component="$component" />
                    <x-ballot-component.desc :component="$component" />
                    <div class="mt-4">
                        <x-ballot-component.form :component="$component" :componentTree="$componentTree"
                            :election="$election" :ballot="$ballot" />
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-center text-muted py-10">{{ __('ballot.no_questions') }}</p>
    @endif
</x-ballot-wrapper>
@endsection
