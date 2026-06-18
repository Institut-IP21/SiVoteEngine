<div class="bg-white border border-line rounded-2xl shadow-[0_1px_2px_rgba(16,30,40,.05)] p-5 sm:p-6 mb-4"
    x-data="{ show: false, info: false }">
    <div class="flex items-baseline justify-between gap-3">
        <h2 class="font-bold text-base sm:text-lg text-ink leading-snug">{{ __('ballot.voteId') }}</h2>
        <button type="button" x-on:click="info = !info" x-bind:aria-expanded="info ? 'true' : 'false'"
            class="flex-shrink-0 text-muted hover:text-ink transition" aria-label="{{ __('ballot.voteId') }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path stroke-linecap="round" d="M12 16v-4" />
                <path stroke-linecap="round" d="M12 8h.01" />
            </svg>
        </button>
    </div>

    <p x-show="info" x-cloak class="mt-2 text-sm text-muted leading-relaxed">{{ __('ballot.code_info') }}</p>

    <input name="code" id="code" readonly value="{{ $code }}" placeholder="{{ __('ballot.voteId') }}"
        type="password" x-bind:type="show ? 'text' : 'password'"
        x-on:mouseover="show = true" x-on:mouseout="show = false"
        x-on:focus="show = true" x-on:blur="show = false"
        class="mt-3 w-full rounded-lg border border-line bg-canvas px-3 py-2.5 font-mono text-ink tracking-wide focus:outline-none focus:ring-2 focus:ring-brand">
</div>
