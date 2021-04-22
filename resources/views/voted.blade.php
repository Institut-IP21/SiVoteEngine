@extends('layouts.main')

@section('title', 'Voted')

@section('body')
    <div class="bg-gray-100 min-h-screen flex items-center flex-col justify-center">
        <div class="max-w-screen-md px-6 py-4 rounded overflow-hidden shadow-md mx-auto bg-white">
            <div class="font-bold text-center justify-between items-baseline">
                <h1 class="text-2xl">{{ __('ballot.vote.registered') }}</h1>
                <p class="mt-2">{{ $ballot->description }}</p>
            </div>
            @if ($pers->photo_url)
                <div class="py-2"></div>
                <div class="flex justify-center">
                    <img src="{{ $pers->photo_url }}" alt="">
                </div>
                <div class="py-2"></div>
            @endif
        </div>
    </div>
@endsection
