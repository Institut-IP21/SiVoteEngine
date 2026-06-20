@extends('layouts.main')

@section('title', __('ballot.result.title'))

@section('body')
<x-ballot-wrapper :pers="$pers">
    <x-ballot-logo :pers="$pers" />

    {{-- Results header — same eyebrow + heading treatment as the voting shell (x-ballot-title). --}}
    <div class="text-center mb-7 sm:mb-9">
        <div class="text-[11px] uppercase tracking-[0.09em] text-muted font-bold mb-1.5">
            {{ __('ballot.result.title') }}
        </div>
        <h1 class="text-[25px] sm:text-[31px] leading-[1.12] font-extrabold tracking-[-0.02em] text-ink"
            style="overflow-wrap:anywhere">
            {{ $ballot->title }}
        </h1>
    </div>

    {{-- Single quorum panel (#6): figures + verdict in ONE box. Secure (green) when met;
         danger (red) and stating "result not binding" (D11) when not — no second warning.
         The component result views still suppress the winner verdict when quorum_met is false. --}}
    @if ($ballot->quorum)
    <div class="w-full max-w-xl mx-auto mb-8 rounded-2xl border p-4 text-center font-semibold
        {{ $ballot->quorum_met ? 'bg-secure-soft text-secure border-secure' : 'bg-danger-soft text-danger border-danger' }}">
        @if ($ballot->quorum_met)
            <div class="text-lg">{{ __('ballot.quorum.met') }}</div>
            <div class="text-sm font-normal mt-1 text-ink">
                {{ trans_choice('ballot.quorum.status', $ballot->votes_count, ['votes' => $ballot->votes_count, 'quorum' => $ballot->quorum]) }}
            </div>
        @else
            <div class="text-lg">{{ __('ballot.quorum.not_met', ['turnout' => $ballot->votes_count, 'quorum' => $ballot->quorum]) }}</div>
        @endif
    </div>
    @endif

    @if ($ballot->components)
        <div class="flex flex-col gap-4">
            @foreach ($ballot->components as $component)
                <div class="bg-white border border-line rounded-2xl shadow-[0_1px_2px_rgba(16,30,40,.05)] p-5 sm:p-6">
                    <x-ballot-component.title :component="$component" />
                    <x-ballot-component.desc :component="$component" />
                    <div class="mt-4">
                        @include($component->result_template, ['component' => $component, 'election' => $election, 'quorumMet' => $ballot->quorum_met])
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-5 mb-10 text-center text-[11px] leading-relaxed text-muted">
            {{ __('ballot.powered_by') }}
        </p>
    @else
        <p class="text-center text-muted py-10">{{ __('ballot.no_questions') }}</p>
    @endif
</x-ballot-wrapper>
@endsection
