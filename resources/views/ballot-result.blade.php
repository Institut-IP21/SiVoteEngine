@extends('layouts.main')

@section('title', __('ballot.result.title'))

@section('body')
<x-ballot-wrapper :pers="$pers">
    <x-ballot-logo :pers="$pers" />

    <div class="text-center w-full mt-10 pb-12">
        <h1 class="text-2xl sm:text-3xl font-bold">
            Rezultati glasovanja:
        </h1>
        <div class="text-1xl sm:text-2xl font-bold" style="overflow-wrap:anywhere">
            {{ $ballot->title }}
        </div>
    </div>

    {{-- Single quorum panel (#6): figures + verdict in ONE box. Green when met; red
         and stating "result not binding" (D11) when not — no second warning. The
         component result views still suppress the winner verdict when quorum_met is false. --}}
    @if ($ballot->quorum)
    <div class="w-full max-w-xl mx-auto mb-8 rounded-lg p-4 text-center font-semibold
        {{ $ballot->quorum_met ? 'bg-green-100 text-green-800 border border-green-300' : 'bg-red-100 text-red-800 border border-red-300' }}">
        @if ($ballot->quorum_met)
            <div class="text-lg">{{ __('ballot.quorum.met') }}</div>
            <div class="text-sm font-normal mt-1">
                {{ trans_choice('ballot.quorum.status', $ballot->votes_count, ['votes' => $ballot->votes_count, 'quorum' => $ballot->quorum]) }}
            </div>
        @else
            <div class="text-lg">{{ __('ballot.quorum.not_met', ['turnout' => $ballot->votes_count, 'quorum' => $ballot->quorum]) }}</div>
        @endif
    </div>
    @endif

    @if ($ballot->components)
    <div class="h-full flex flex-col mt-3 sm:mt-8">
        @foreach ($ballot->components as $component)
        <div class="w-full rounded overflow-hidden shadow bg-white mb-10 p-5 sm:px-8 sm:pt-7 sm:pb-8">
            <x-ballot-component.title :component="$component" />

            <x-ballot-component.desc :component="$component" />
            <div class="px-7">
                @include($component->result_template, ['component' => $component, 'election' => $election, 'quorumMet' => $ballot->quorum_met])
            </div>
        </div>
        @endforeach
    </div>
    @else
    <span class="mx-auto">No components!</span>
    @endif
</x-ballot-wrapper>
@endsection
