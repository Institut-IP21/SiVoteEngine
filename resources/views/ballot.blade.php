@extends('layouts.main')

@section('title', 'Ballot')

@section('body')
    <div id="app" class="bg-gray-100">
        <div class="py-2"></div>
        <div class="max-w-screen-md text-center mx-auto">
            <div class="w-full py-3 rounded overflow-hidden shadow-md mx-auto bg-white">
                <h1 class="text-2xl border-b pb-3">Ballot {{ $ballot->title }}</h1>
                @if ($ballot->description)
                    <p class="mt-2">{{ $ballot->description }}</p>
                @endif
            </div>
        </div>
        <div class="py-2"></div>
        @if ($pers->photo_url)
            <div class="flex justify-center">
                <img src="{{ $pers->photo_url }}" alt="">
            </div>
        @endif
        <div class="py-2"></div>
        @if ($ballot->components)
            <form class="max-w-screen-md mx-auto h-full flex flex-col"
                action="/election/{{ $ballot->election->id }}/ballot/{{ $ballot->id }}" method="post">
                @csrf
                <div class="w-full rounded overflow-hidden shadow-md mx-auto bg-white">
                    <div class="px-6 py-4">
                        <div class="mb-6 font-bold text-xl flex justify-between items-baseline">
                            <span> Glasovalna koda</span>
                            <span class="font-light text-base text-right"></span>
                        </div>
                        <input name="code" readonly
                            class="shadow appearance-none border rounded w-full py-2 px-3 leading-tight focus:outline-none"
                            id="code" type="text" placeholder="Koda" value="{{ $code }}">
                    </div>
                </div>
                @foreach ($ballot->components as $component)
                    <div class="w-full rounded overflow-hidden shadow-md mx-auto bg-white">
                        <div class="py-6">
                            <div class="px-7 mb-6 pb-5 font-bold text-xl flex justify-between items-baseline border-b">
                                <span>{{ $component->title }}</span>
                            </div>
                            <p class="px-7 mb-6 pb-5 border-b text-justify">{{ $component->description }}</p>
                            <div class="px-7">
                                @include($component->form_template, ['component' => $component, 'election' => $election])
                            </div>
                        </div>
                    </div>
                    <div class="py-6"></div>
                @endforeach
                <div class="my-6 text-center">
                    <button type="submit" class="btn btn-blue w-full">Submit</button>
                </div>
            </form>
        @else
            <span>No components!</span>
        @endif
    </div>
@endsection
