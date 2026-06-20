@php
    // Audit/meta keys are not option tallies and must not render as option rows.
    $meta = ['continuing', 'exhausted', 'exhausted_running', 'eliminated', 'eliminated_previously', 'winner', 'tied'];
    $tallies = array_filter($round, fn ($v, $k) => is_int($v) && !in_array($k, $meta, true), ARRAY_FILTER_USE_BOTH);
    $minTally = count($tallies) ? min($tallies) : null;
    $winner = $round['winner'] ?? null;
    $eliminated = $round['eliminated'] ?? null;
@endphp
<div class="round px-1 pt-2 flex">
    <div class="round_state flex flex-col justify-center w-full">
        <b class="border bg-gray-200 row px-8 py-2">{{ __('components.rankedchoice.round') }}
            {{ $round_prefix . ($i + 1) }}</b>
        @foreach ($tallies as $name => $votes)
        <div class="border-t row px-2 py-1
                    {{ $winner !== null && (string) $name === (string) $winner ? 'bg-green-200' : '' }}
                    {{ $eliminated !== null && in_array((string) $name, array_map('trim', explode(',', (string) $eliminated)), true) ? 'bg-red-200' : '' }}
                    {{ ($winner === null && $eliminated === null && $minTally === $votes) ? 'bg-yellow-200' : '' }}">
            <span class="flex-1" style="min-width:0;overflow-wrap:anywhere">{{ $name }}</span>
            <span class="flex-shrink-0 text-right pl-2">{{ $votes }}</span>
        </div>
        @endforeach

        @if ($eliminated !== null)
        <div class="border-t row px-2 py-1 bg-red-200">
            <span class="flex-shrink-0"><strong>{{ __('components.eliminated') }}:</strong></span>
            <span class="flex-1 text-right pl-2" style="min-width:0;overflow-wrap:anywhere">{{ $eliminated }}</span>
        </div>
        @endif

        @if (is_string($winner))
        <div class="border-t row px-2 py-1 bg-green-200">
            <span class="flex-shrink-0"><strong>{{ __('components.winner') }}:</strong></span>
            <span class="flex-1 text-right pl-2" style="min-width:0;overflow-wrap:anywhere">{{ $winner }}</span>
        </div>
        @endif

        {{-- Continuing/exhausted figures (D7/D8). --}}
        <div class="border-t row px-2 py-1 text-sm text-gray-600">
            <span class="flex-1">{{ __('components.rankedchoice.continuing') }}</span>
            <span class="flex-1 text-right">{{ $round['continuing'] ?? 0 }}</span>
        </div>
        <div class="row px-2 py-1 text-sm text-gray-600">
            <span class="flex-1">{{ __('components.rankedchoice.exhausted') }}</span>
            <span class="flex-1 text-right">{{ $round['exhausted'] ?? 0 }}</span>
        </div>
    </div>
</div>
