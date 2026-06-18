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
                    @php $typeName = $componentTree[$component->type][$component->version]['strings']['name'] ?? null; @endphp
                    <div class="bg-white border border-line rounded-2xl shadow-[0_1px_2px_rgba(16,30,40,.05)] p-5 sm:p-6">
                        <x-ballot-component.title :component="$component" :type-name="$typeName" />
                        <x-ballot-component.desc :component="$component" />
                        <div class="mt-4">
                            <x-ballot-component.form :component="$component" :componentTree="$componentTree"
                                :election="$election" :ballot="$ballot" />
                        </div>
                    </div>
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
