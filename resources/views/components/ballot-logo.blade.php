@if ($pers && $pers->photo_url)
    <div class="flex justify-center mb-5 sm:mb-6">
        <img src="{{ $pers->photo_url }}" alt="" class="max-h-16 sm:max-h-20 w-auto">
    </div>
@endif
