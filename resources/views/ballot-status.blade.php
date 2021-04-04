@extends('layouts.main')

@section('title', 'Ballot Results')

@section('body')
<div class="container mx-auto">
    <div class="max-w-md text-center mx-auto">
        <h1 class="text-2xl">Ballot {{ $ballot->title }}</h1>
        <p>{{ $ballot->description }}</p>
    </div>
    @if ($ballot->components)
    <div class="max-w-md mx-auto">
    @foreach ($ballot->components as $component)
    <div class="mt-3 rounded overflow-hidden shadow mx-auto">
        <div class="px-6 py-4">
            <div class="mb-6 font-bold text-xl flex justify-between items-baseline">
                <span>{{ $component->title }}</span>
                <span class="font-light text-base text-right">{{ $component->type }}<b class="text-blue-400">{{ $component->version }}</b></span>
            </div>
            <p class="mb-6 text-justify">{{ $component->description }}</p>
            @include($component->result_template, ['component' => $component, 'results' => $results])
        </div>
    </div>
    @endforeach
    </div>
    @else
    <span>No components!</span>
    @endif
</div>
@endsection
