@extends('layouts.main')

@section('title', 'Ballot')

@section('body')
<x-ballot-wrapper>
    <x-ballot-logo :pers="$pers" />

    <x-ballot-title :ballot="$ballot" />

    <form class="max-w-screen-md mx-auto h-full flex flex-col"
        action="/election/{{ $ballot->election->id }}/ballot/{{ $ballot->id }}" method="post">
        @csrf

        <x-ballot-code :code="$code" />

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
        <div class="mt-6 mb-12 w-full">
            <button type="submit"
                class="block w-1/2 mx-auto border border-black bg-blue-700 text-white uppercase px-6 py-4 text-xl font-bold">
                Oddaj glas
            </button>
        </div>

        <div class="mt-6 mb-20 text-center w-full text-sm font-thin">
            Glasovanje se izvaja z odprtokodnim sistemom za anonimno glasovanje <a class="underline"
                href="https://github.com/Institut-IP21">SiVote</a> in platforme <a class="underline"
                href="https://eglasovanje.si">eGlasovanje.si</a>.
            <br />
            Vaš glas bo zabeležen anonimno.
        </div>

        @else
        <span>No components!</span>
        @endif

    </form>
</x-ballot-wrapper>
@endsection