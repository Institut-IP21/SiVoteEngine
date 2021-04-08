@extends('layouts.main')

@section('title', 'Voted')

@section('body')
    <div class="container mx-auto">
        <div class="max-w-md text-center mx-auto">
            <h1 class="text-2xl">{{ __('Your vote has been registered') }}</h1>
            <a class="underline pointer" href="/election/{{ $election->id }}/ballot/{{ $ballot->id }}/result">
                {{ $ballot->title }}
            </a>
            <p>{{ $ballot->description }}</p>
        </div>
    </div>
@endsection
