@extends('layouts.main')

@section('title', __('ballot.vote.registered'))

@section('body')
<div class="bg-gray-100 min-h-screen flex items-center flex-col justify-center">

    <div class="max-w-screen-md px-6 pb-12 mx-auto">

        @if ($pers && $pers->photo_url)
        <div class="flex justify-center pb-12">
            <img src="{{ $pers->photo_url }}" class="max-h-20 sm:max-h-28">
        </div>
        @endif

        <div class="text-center justify-between items-baseline">
            <h1 class="text-2xl font-bold">{{ __('ballot.vote.registered') }}</h1>
            <p class="mt-2">To stran lahko zaprete.</p>
        </div>
    </div>

</div>
@endsection