<div class="text-center w-full mt-10 pb-12">
    <h1 class="text-2xl sm:text-3xl font-bold">
        {{ $ballot->title }}
    </h1>
    <div class="text-center text-lg sm:text-xl pb-1 font-thin text-gray-500">
        {{ strtolower(__('ballot.single')) }}
    </div>
    @if ($ballot->description)
    <p class="px-2 sm:px-7 pt-6 text-justify text-base sm:text-left">{{ $ballot->description }}</p>
    @endif
</div>