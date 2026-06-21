{{-- Canonical ranked-choice option label. The single source for the option name's
     colour + overflow handling, shared by the ranked row, the unranked row and the
     static preview so they can't visually diverge (this is the span that drifted to
     white-on-white once they were maintained separately).
     Params: $name (string); $bold (bool, default false); $ariaLabel (string, optional). --}}
<span class="flex-1 min-w-0 text-[15px] {{ ($bold ?? false) ? 'font-semibold ' : '' }}text-ink leading-snug" style="overflow-wrap:anywhere"@isset($ariaLabel) aria-label="{{ $ariaLabel }}"@endisset>{{ $name }}</span>
