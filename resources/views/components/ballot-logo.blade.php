@if ($pers && $pers->photo_url)
    <div class="flex justify-center mb-5 sm:mb-6">
        <span class="inline-flex items-center bg-white border border-line rounded-xl px-4 py-2.5 shadow-[0_1px_2px_rgba(16,30,40,.04)]">
            <img src="{{ $pers->photo_url }}" alt="" class="max-h-16 sm:max-h-20 w-auto">
        </span>
    </div>
@endif
