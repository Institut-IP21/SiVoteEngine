@extends('layouts.main')

@section('title', 'Ballot Results')

@section('body')
<x-ballot-wrapper>
    <x-ballot-logo :pers="$pers" />

    <div class="text-center w-full mt-10 pb-12">
        <h1 class="text-2xl sm:text-3xl font-bold">
            Rezultati glasovanja:
        </h1>
        <div class="text-1xl sm:text-2xl font-bold">
            {{ $ballot->title }}
        </div>
    </div>

    @if ($ballot->components)
    <div class="h-full flex flex-col mt-3 sm:mt-8">
        @foreach ($ballot->components as $component)
        <div class="w-full rounded overflow-hidden shadow bg-white mb-10 p-5 sm:px-8 sm:pt-7 sm:pb-8">
            <x-ballot-component.title :component="$component" />

            <x-ballot-component.desc :component="$component" />
            <div class="px-7">
                @include($component->result_template, ['component' => $component, 'election' => $election])
            </div>
        </div>
        @endforeach
    </div>
    @else
    <span class="mx-auto">No components!</span>
    @endif
</x-ballot-wrapper>
@endsection
