<div class="-mx-6 d-flex flex-wrap">
    @foreach ($results[$component->id]['results'] as $i => $round)
        <div class="min-w-1/3 max-w-1/3 px-1 pt-2">
            <b class="border bg-gray-200 row px-8 py-2">Round {{ $i + 1 }}</b>
            @foreach ($round as $name => $options)
                <div
                    class="border-t row px-2 py-1 {{ $name === 'eliminated' ? 'bg-red-200' : '' }} {{ $name === 'winner' ? 'bg-green-200' : '' }}">
                    <span class="flex-1">
                        {{ $name }}
                    </span>
                    <span class="flex-1 text-right">{{ $options }} </span>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
