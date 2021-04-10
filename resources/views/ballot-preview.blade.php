@extends('layouts.main')

@section('title', 'Ballot')

@section('body')
    <div id="app" class="bg-gray-100">
        <div class="max-w-screen-md text-center mx-auto mb-4">
            <h1 class="text-2xl">Ballot {{ $ballot->title }}</h1>
            <p>{{ $ballot->description }}</p>
        </div>
        @if ($ballot->components)
            <div class="max-w-screen-md mx-auto h-full flex flex-col">
                @foreach ($ballot->components as $component)
                    <div class="w-full rounded overflow-hidden shadow-2xl mx-auto bg-white">
                        <div class="py-6">
                            <div class="px-7 mb-6 pb-5 font-bold text-xl flex justify-between items-baseline border-b">
                                <span>{{ $component->title }}</span>
                                <span class="font-light text-base text-right">{{ $component->type }}<b
                                        class="text-blue-400">{{ $component->version }}</b></span>
                            </div>
                            <p class="px-7 mb-6 pb-5 border-b text-justify">{{ $component->description }}</p>
                            <div class="px-7">
                                @include($component->form_template, ['component' => $component, 'election' => $election])
                            </div>
                        </div>
                    </div>
                    <div class="py-6"></div>
                @endforeach
            </div>
        @else
            <span>No components!</span>
        @endif
    </div>
@endsection
