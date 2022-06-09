<rankedchoice component="{{ $component->id }}" :options='@json($component->options)' inline-template>
    <div>
        <p>
            {{ __('components.rankedchoice.intro', ['options' => count($component->options)]) }}
        </p>
        <hr class="py-2" />
        <p class="pb-2">
            {{ __('components.rankedchoice.state') }}
        </p>
        <div class="row border border-blue-300 d-flex mb-1" v-for="(option, i) of selected" :key="option.rank">
            <div class="rank border-l border-r border-blue-300 rank text-2xl flex-1 py-2 px-4">
                @{{ option . rank }}
            </div>
            <div class="border-r border-blue-300 flex-3 text-2xl py-2 px-4">
                @{{ option . name }}
            </div>
            <div class="border-r border-blue-300 flex-1 text-2xl buttons d-flex">
                <button type="button"
                    class="btn disabled:opacity-30 hover:bg-blue-100 hover:bg-opacity-25 disabled:bg-transparent"
                    :disabled="i === 0 || selected.length < 2" @click="up(option, i)"
                    :class="{
                        'cursor-not-allowed': i === 0 || selected.length < 2
                    }">
                    <i>{{ __('components.rankedchoice.UP') }}</i>
                </button>
                <button type="button"
                    class="btn disabled:opacity-30 hover:bg-blue-100 hover:bg-opacity-25 disabled:bg-transparent"
                    @click="down(option, i)" :disabled="i === selected.length - 1 || selected.length < 2"
                    :class="{
                        'cursor-not-allowed':
                            i === selected.length - 1 || selected.length < 2
                    }">
                    <i>{{ __('components.rankedchoice.DOWN') }}</i>
                </button>
                <button class="btn border-l bg-red-200 text-red-600 hover:text-white hover:bg-red-600 rounded-none"
                    @click="remove(option, i)">
                    <i>X</i>
                </button>
            </div>
        </div>
        <div class="row border border-gray-400 mb-1 d-flex cursor-pointer" v-for="(option, i) of unselected"
            :key="option.rank" @click="select(option, i)">
            <div class="hover:bg-blue-100 hover:bg-opacity-25 text-2xl flex-2 py-2 px-4">
                @{{ option . name }}
            </div>
        </div>
        <input type="hidden" v-for="rankee in selected" :key="rankee.name" :name="component + '[]'"
            :value="rankee.name" />
    </div>
</rankedchoice>
