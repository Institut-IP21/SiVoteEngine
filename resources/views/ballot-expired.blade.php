@extends('layouts.main')

@section('title', __('ballot.expired'))

@section('body')
    <div class="bg-gray-100 min-h-screen flex items-center flex-col justify-center">
        <div class="max-w-screen-md px-6 py-4 rounded overflow-hidden shadow-md mx-auto bg-white">
            <div class="font-bold text-center justify-between items-baseline">
                <h1 class="text-2xl">{{ __('ballot.expired') }}</h1>
            </div>
        </div>
    </div>
@endsection
