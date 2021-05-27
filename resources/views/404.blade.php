@extends('layouts.main')

@section('title', __('Not found'))

@section('body')
    <div class="bg-gray-100 min-h-screen flex items-center flex-col justify-center">
        <div class="max-w-screen-md px-6 py-4 rounded overflow-hidden shadow-md mx-auto bg-white">
            <div class="font-bold text-center justify-between items-baseline">
                <h1 class="text-2xl">{{ __('Not found') }}</h1>
            </div>
        </div>
    </div>
@endsection
