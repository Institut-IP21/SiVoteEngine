@extends('layouts.main')

@section('title', 'Ballot Results')

@section('body')
<div id="app" class="min-h-screen bg-gray-100">
    <div class="py-2"></div>
    <div class="max-w-screen-md mx-auto">
        <div class="w-full pt-6 text-center rounded overflow-hidden shadow-md mx-auto bg-white">
            <h1 class="text-2xl pb-3">{{ $ballot->title }}</h1>
            @if ($ballot->description)
                <p class="p-6">{{ $ballot->description }}</p>
            @endif
        </div>
    </div>
    @if ($pers && $pers->photo_url)
        <div class="py-2"></div>
        <div class="flex justify-center">
            <img src="{{ $pers->photo_url }}" alt="">
        </div>
    @endif
    <div class="py-6"></div>
    @if ($ballot->components)
        <div class="max-w-screen-md mx-auto h-full flex flex-col">
            @foreach ($ballot->components as $component)
                <div class="w-full rounded overflow-hidden shadow-md mx-auto bg-white">
                    <div class="py-6">
                        <div class="px-7 mb-6 pb-6 font-bold text-xl flex justify-between items-baseline border-b">
                            <span>{{ $component->title }}</span>
                        </div>
                        @if ($component->description)
                            <p class="px-7 mb-6 pb-6 border-b text-justify">{{ $component->description }}</p>
                        @endif
                        <div class="px-7">
                            @include($component->result_template, ['component' => $component, 'election' => $election])
                        </div>
                    </div>
                </div>
                <div class="py-6"></div>
            @endforeach
        </div>
    @else
        <span class="mx-auto">No components!</span>
    @endif
</div>
@endsection
