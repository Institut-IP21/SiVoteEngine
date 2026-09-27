@props(['component'])

{{-- The type label (top-right of every ballot element) is the component's localized
     name, resolved from the registry via $component->type_name — the single source of
     truth (each component's getStrings()['name']). It doubles as the trigger for an info
     modal giving the plain-language "how it works" explanation of that method
     ($component->lay_explanation, same registry-sourced single-source pattern), shown on
     both the live ballot and the public results page (both render this component).
     Self-contained Alpine component: Alpine is self-hosted and started in app.js (see
     resources/js/app.js), loaded unconditionally on every page through layouts.main — not
     dependent on Livewire being present, so this works on the plain-Blade results page too. --}}
<div class="flex items-baseline justify-between gap-3"
    @if ($component->type_name) x-data="{ infoOpen: false }" @endif>
    <h2 class="font-bold text-base sm:text-lg text-ink leading-snug" style="min-width:0">{{ $component->title }}</h2>
    @if ($component->type_name)
        {{-- Let the type label wrap instead of overflowing the ballot on narrow (phone)
             widths: no flex-shrink-0 (inline styles, so no new utility classes to
             compile) and allow the words to break. --}}
        <button type="button" x-on:click="infoOpen = true"
            aria-haspopup="dialog"
            aria-label="{{ __('ballot.type_info', ['name' => $component->type_name]) }}"
            class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-[0.05em] text-muted hover:text-brand-dark transition"
            style="overflow-wrap:anywhere;text-align:right">
            <span>{{ $component->type_name }}</span>
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="9" />
                <path stroke-linecap="round" d="M12 16v-4" />
                <path stroke-linecap="round" d="M12 8h.01" />
            </svg>
        </button>

        <template x-teleport="body">
            <div x-show="infoOpen" x-cloak x-on:keydown.escape.window="infoOpen = false"
                class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" x-on:click="infoOpen = false" aria-hidden="true"></div>
                <div role="dialog" aria-modal="true" aria-label="{{ $component->type_name }}"
                    class="relative w-full max-w-sm rounded-2xl bg-white border border-line shadow-[0_8px_30px_rgba(16,30,40,.18)] p-5 sm:p-6">
                    <div class="flex items-baseline justify-between gap-3 mb-3">
                        <h3 class="font-bold text-base sm:text-lg text-ink leading-snug">{{ $component->type_name }}</h3>
                        <button type="button" x-on:click="infoOpen = false"
                            class="flex-shrink-0 -mr-1 -mt-1 p-1 text-muted hover:text-ink transition"
                            aria-label="{{ __('ballot.close') }}">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-muted leading-relaxed">{{ $component->lay_explanation }}</p>
                </div>
            </div>
        </template>
    @endif
</div>
