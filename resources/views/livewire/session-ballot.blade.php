<div id="app" class="bg-gray-100 min-h-screen" wire:poll.5000ms>
    <div class="py-2"></div>
    <div class="max-w-screen-md text-center mx-auto">
        <div class="w-full py-3 rounded overflow-hidden shadow-md mx-auto bg-white">
            <h1 class="text-2xl">{{ $ballot->title }}</h1>
            @if ($ballot->description)
                <p class="mt-2 border-t pt-3">{{ $ballot->description }}</p>
            @endif
        </div>
    </div>
    <div class="py-2"></div>
    @if (session('success'))
        <div class="max-w-screen-md text-center mx-auto bg-green-600 text-white">
            <div class="w-full py-3 rounded overflow-hidden shadow-md mx-auto">
                <h1 class="text-2xl">{{ session('success') }}</h1>
            </div>
        </div>
        <div class="py-2"></div>
    @endif
    <form class="max-w-screen-md mx-auto h-full flex flex-col"
        action="/election/{{ $ballot->election->id }}/ballot/{{ $ballot->id }}/component/" method="post">
        @csrf
        <div class="w-full rounded overflow-hidden shadow-md mx-auto bg-white mb-3">
            <div class="px-6 py-4">
                <div class="mb-6 font-bold text-xl flex justify-between items-baseline">
                    <span> Glasovalna koda</span>
                    <span class="font-light text-base text-right"></span>
                </div>
                <input name="code" readonly
                    x-bind:type="show ? 'text' : 'password'"
                    x-data="{ show: false }"
                    x-on:mouseover="show = true" x-on:mouseout="show = false"
                    class="shadow appearance-none border rounded w-full py-2 px-3 leading-tight focus:outline-none"
                    id="code" type="password" placeholder="Koda" value="{{ $code }}">
            </div>
        </div>
        <div class="py-2"></div>
        @if (count($activeComponents) > 0)
            @foreach ($activeComponents as $component)
                <div class="w-full rounded overflow-hidden shadow-md mx-auto bg-white">
                    <div class="py-6">
                        <div class="px-7 mb-6 pb-5 font-bold text-xl flex justify-between items-baseline border-b">
                            <span>{{ $component->title }}</span>
                        </div>
                        @if ($component->description)
                            <p class="px-7 mb-6 pb-5 border-b text-justify">{{ $component->description }}</p>
                        @endif
                        <div class="px-7">
                            @if ($componentTree[$component->type][$component->version]['livewireForm'])
                                @livewire(Str::kebab($component->type) . '-livewire', ['ballot' => $ballot, 'component'
                                => $component])
                            @else
                                @include($component->form_template, ['component' => $component, 'election' =>
                                $election])
                            @endif
                        </div>
                    </div>
                </div>
                <div class="py-6"></div>
            @endforeach
            @if ($code !== 'preview-mode')
                <div class="my-6 text-center">
                    <button type="submit" class="btn btn-blue w-full">Oddaj glas</button>
                </div>
            @endif
        @else
        <div class="max-w-screen-md mx-auto overflow-hidden shadow-md mx-auto bg-white">
            <div class="px-6 py-4">
                <span>Trenutno ni odprtih vprašanj</span>
            </div>
        </div>
        @endif
    </form>
</div>
