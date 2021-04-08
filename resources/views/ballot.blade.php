@extends('layouts.main')

@section('title', 'Ballot')

@section('body')
    <div id="app" class="container mx-auto">
        <div class="max-w-screen-md	text-center mx-auto">
            <h1 class="text-2xl">Ballot {{ $ballot->title }}</h1>
            <p>{{ $ballot->description }}</p>
        </div>
        @if ($ballot->components)
            <form class="max-w-screen-md mx-auto"
                action="/election/{{ $ballot->election->id }}/ballot/{{ $ballot->id }}" method="post">
                @csrf
                <div class="mt-3 rounded overflow-hidden shadow mx-auto">
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
                    <div class="mt-3 rounded overflow-hidden shadow mx-auto">
                        <div class="px-6 py-4">
                            <div class="mb-6 font-bold text-xl flex justify-between items-baseline">
                                <span>{{ $component->title }}</span>
                                <span class="font-light text-base text-right">{{ $component->type }}<b
                                        class="text-blue-400">{{ $component->version }}</b></span>
                            </div>
                            <p class="mb-6 text-justify">{{ $component->description }}</p>
                            @include($component->form_template, ['component' => $component, 'election' => $election])
                        </div>
                    </div>
                @endforeach
                <div class="mt-6 text-center mx-auto">
                    <button type="submit" class="btn btn-blue">Submit</button>
                </div>
            </form>
        @else
            <span>No components!</span>
        @endif
    </div>
@endsection
