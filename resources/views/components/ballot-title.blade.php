<div class="text-center mb-7 sm:mb-9">
    <div class="text-[11px] uppercase tracking-[0.09em] text-muted font-bold mb-1.5">
        {{ __('ballot.single') }}
    </div>
    <h1 class="text-[25px] sm:text-[31px] leading-[1.12] font-extrabold tracking-[-0.02em] text-ink">
        {{ $ballot->title }}
    </h1>
    @if ($ballot->description)
        <p class="max-w-xl mx-auto mt-3 text-sm sm:text-[15px] leading-relaxed text-muted text-left sm:text-center">
            {{ $ballot->description }}
        </p>
    @endif
</div>
