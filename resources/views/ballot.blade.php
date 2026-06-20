@extends('layouts.main')

@section('title', __('ballot.single'))

@section('body')
<x-ballot-wrapper :pers="$pers">
    <x-ballot-logo :pers="$pers" />

    <x-ballot-title :ballot="$ballot" />

    <form class="flex flex-col"
        action="/election/{{ $ballot->election->id }}/ballot/{{ $ballot->id }}" method="post">
        @csrf

        <x-ballot-code :code="$code" />

        @if ($ballot->components)
            <div class="flex flex-col gap-4">
                @foreach ($ballot->components as $component)
                    <x-ballot-component-card :component="$component" :election="$election"
                        :ballot="$ballot" :componentTree="$componentTree" />
                @endforeach
            </div>

            <button type="submit" class="ballot-submit mt-6">{{ __('ballot.submit') }}</button>

            <p class="mt-5 mb-10 text-center text-[11px] leading-relaxed text-muted">
                {{ __('ballot.anonymous') }}<br>
                {{ __('ballot.powered_by') }}
            </p>
        @else
            <p class="text-center text-muted py-10">{{ __('ballot.no_questions') }}</p>
        @endif
    </form>
</x-ballot-wrapper>
@endsection
