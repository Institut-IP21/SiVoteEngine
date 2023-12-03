@if ($pers && $pers->photo_url)
<div class="flex justify-center pb-4 pt-10">
    <img src="{{ $pers->photo_url }}" alt="" class="max-h-28 sm:max-h-36">
</div>
@endif