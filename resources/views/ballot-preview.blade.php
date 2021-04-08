@extends('layouts.main')

@section('title', 'Ballot')

@section('body')
<div id="app" class="container mx-auto">
    <div class="max-w-screen-md text-center mx-auto">
        <h1 class="text-2xl">Ballot {{ $ballot->title }}</h1>
        <p>{{ $ballot->description }}</p>
    </div>
    @if ($ballot->components)
    <div class="max-w-screen-md mx-auto">
        @foreach ($ballot->components as $component)
        <div class="mt-3 rounded overflow-hidden shadow mx-auto">
            <div class="px-6 py-4">
                <div class="mb-6 font-bold text-xl flex justify-between items-baseline">
                    <span>{{ $component->title }}</span>
                    <span class="font-light text-base text-right">{{ $component->type }}<b class="text-blue-400">{{ $component->version }}</b></span>
                </div>
                <p class="mb-6 text-justify">{{ $component->description }}</p>
                @include($component->form_template, ['component' => $component, 'election' => $election])
            </div>
        </div>
        @endforeach
    </div>
    @else
    <span>No components!</span>
    @endif
</div>
@endsection
