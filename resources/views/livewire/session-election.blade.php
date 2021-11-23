<div class="bg-gray-100 min-h-screen">
    <div class="py-2"></div>
    <div class="max-w-screen-md text-center mx-auto">
        <div class="w-full py-3 rounded overflow-hidden shadow-md mx-auto bg-white">
            <h1 class="text-2xl border-b pb-3">Session Election {{ $election->title }}</h1>
        </div>
    </div>
    <div class="max-w-screen-md text-center mx-auto">
        <div class="w-full py-3 rounded overflow-hidden shadow-md mx-auto bg-white">
            <h2 class="text-2xl border-b pb-3">Ballots</h2>
            <div class="flex flex-wrap" wire:poll.5000ms>
                @foreach ($election->ballots as $ballot)
                    @if ($ballot->active)
                        <div class="w-full p-3">
                            <div class="bg-white rounded-lg shadow-lg p-3">
                                <div class="flex flex-col break-words">
                                    <div class="font-semibold text-xl mb-2">{{ $ballot->title }}</div>
                                    <div class="text-gray-700 text-base">{{ $ballot->description }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
