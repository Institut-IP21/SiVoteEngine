@extends('layouts.main')

@section('title', __('ballot.single') . ' - eGlasovanje.si')

@section('body')
<x-ballot-wrapper :pers="$pers">
    {{-- Standalone preview warning. Hidden when embedded (?embed=1) in the web_app
         builder's live-preview iframe; shown on the full-page preview link. --}}
    @unless (request()->boolean('embed'))
        <x-ballot-alert class="mb-6">{{ __('ballot.preview.warning') }}</x-ballot-alert>
    @endunless

    <x-ballot-logo :pers="$pers" />

    <x-ballot-title :ballot="$ballot" />

    {{-- The vote-code card always sits at the top of the ballot (as on the live ballot);
         shown here with a placeholder so the preview matches what voters see. --}}
    <x-ballot-code :code="'XXXX–XXXX–XXXX'" />

    @if ($ballot->components)
        <div class="flex flex-col gap-4">
            @foreach ($ballot->components as $component)
                <x-ballot-component-card :component="$component" :election="$election"
                    :ballot="$ballot" :componentTree="$componentTree" />
            @endforeach
        </div>
    @else
        <p class="text-center text-muted py-10">{{ __('ballot.no_questions') }}</p>
    @endif
</x-ballot-wrapper>
@endsection
