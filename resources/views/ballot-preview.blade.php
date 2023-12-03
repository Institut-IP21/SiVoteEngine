@extends('layouts.main')

@section('title', __('ballot.single') . ' - eGlasovanje.si')

@section('body')
<x-ballot-wrapper>
    <div class="max-w-screen-md py-4 px-2 text-center rounded overflow-hidden shadow mx-auto bg-red-400 text-white">
        {{ __('ballot.preview.warning') }}
    </div>

    <x-ballot-logo :pers="$pers" />

    <x-ballot-title :ballot="$ballot" />

    @if ($ballot->components)
    <div class="h-full flex flex-col mt-3 sm:mt-8">
        @foreach ($ballot->components as $component)
        <div class="w-full rounded overflow-hidden shadow bg-white mb-10 p-5 sm:px-8 sm:pt-7 sm:pb-8">
            <x-ballot-component.title :component="$component" />

            <x-ballot-component.desc :component="$component" />

            <x-ballot-component.form :component="$component" :componentTree="$componentTree" :election="$election"
                :ballot="$ballot" />
        </div>
        @endforeach
    </div>

    @else
    <span>No components!</span>
    @endif

</x-ballot-wrapper>
@endsection