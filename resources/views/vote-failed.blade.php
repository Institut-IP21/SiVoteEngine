@extends('layouts.main')

@section('title', __('ballot.vote.failed.title'))

@section('body')
<div class="bg-gray-100 min-h-screen flex items-center flex-col justify-center">
    <div class="max-w-screen-md w-full px-6 pb-12 mx-auto">
        <div class="text-center">
            <h1 class="text-2xl font-bold text-red-700">{{ __('ballot.vote.failed.heading') }}</h1>
            <p class="mt-2 text-gray-700">{{ __('ballot.vote.failed.intro') }}</p>
        </div>

        @if ($errors->isNotEmpty())
            <ul class="mt-6 mx-auto max-w-md text-left list-disc list-inside text-red-700">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
