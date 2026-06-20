{{-- Live "session" voting page: same redesigned shell as the standalone ballot, but the
     admin opens/closes questions during a meeting, so it polls and renders only the
     currently-active components. wire:poll lives on the shell root (single Livewire root). --}}
<x-ballot-wrapper :pers="$pers" wire:poll.5000ms>
    <x-ballot-logo :pers="$pers" />

    <x-ballot-title :ballot="$ballot" />

    @if (session('success'))
        <div class="mb-4 flex items-center justify-center gap-2 rounded-2xl border border-brand bg-brand-soft px-5 py-4 text-center font-bold text-brand-fg"
            data-session-success>
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <form class="flex flex-col"
        action="/election/{{ $ballot->election->id }}/ballot/{{ $ballot->id }}/component/" method="post">
        @csrf

        <x-ballot-code :code="$code" />

        @if (count($activeComponents) > 0)
            <div class="flex flex-col gap-4">
                @foreach ($activeComponents as $component)
                    <x-ballot-component-card :component="$component" :election="$election"
                        :ballot="$ballot" :componentTree="$componentTree" />
                @endforeach
            </div>

            @if ($code !== 'preview-mode')
                <button type="submit" class="ballot-submit mt-6">{{ __('ballot.submit') }}</button>
            @endif

            <p class="mt-5 mb-10 text-center text-[11px] leading-relaxed text-muted">
                {{ __('ballot.anonymous') }}<br>
                {{ __('ballot.powered_by') }}
            </p>
        @else
            <p class="text-center text-muted py-10">{{ __('ballot.session.no_open_questions') }}</p>
        @endif
    </form>
</x-ballot-wrapper>
