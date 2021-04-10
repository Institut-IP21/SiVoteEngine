<template>
    <div>
        <p>
            There are {{ rankees.length }} options. Rank the options in order of
            your choice. You may rank as few or as many as you wish.
        </p>
        <hr class="py-2" />
        <p class="pb-2">
            You have ranked {{ selected.length }}, you may rank
            {{ rankees.length - selected.length }} more.
        </p>
        <div
            class="row border border-blue-300 d-flex mb-1"
            v-for="(option, i) of selected"
            :key="option.rank"
        >
            <div
                class="rank border-l border-r border-blue-300 rank text-2xl flex-1 py-2 px-4"
            >
                {{ option.rank }}
            </div>
            <div class="border-r border-blue-300 flex-3 text-2xl py-2 px-4">
                {{ option.name }}
            </div>
            <div
                class="border-r border-blue-300 flex-1 text-2xl buttons d-flex"
            >
                <button
                    type="button"
                    class="btn disabled:opacity-30 hover:bg-blue-100 hover:bg-opacity-25 disabled:bg-transparent"
                    :disabled="i === 0 || selected.length < 2"
                    @click="up(option, i)"
                    :class="{
                        'cursor-not-allowed': i === 0 || selected.length < 2
                    }"
                >
                    <i>UP</i>
                </button>
                <button
                    type="button"
                    class="btn disabled:opacity-30 hover:bg-blue-100 hover:bg-opacity-25 disabled:bg-transparent"
                    @click="down(option, i)"
                    :disabled="i === selected.length - 1 || selected.length < 2"
                    :class="{
                        'cursor-not-allowed':
                            i === selected.length - 1 || selected.length < 2
                    }"
                >
                    <i>DOWN</i>
                </button>
            </div>
        </div>
        <div
            class="row border border-gray-400 mb-1 d-flex cursor-pointer"
            v-for="(option, i) of unselected"
            :key="option.rank"
            @click="select(option, i)"
        >
            <div
                class="hover:bg-blue-100 hover:bg-opacity-25 text-2xl flex-2 py-2 px-4"
            >
                {{ option.name }}
            </div>
        </div>
        <input
            type="hidden"
            v-for="rankee in selected"
            :key="rankee.name"
            :name="component + '[]'"
            :value="rankee.name"
        />
    </div>
</template>

<script>
export default {
    data() {
        return {
            rankees: this.options.map((option, i) => {
                return {
                    name: option,
                    rank: undefined
                };
            })
        };
    },
    props: {
        options: {
            type: Array
        },
        component: {
            type: String
        }
    },
    computed: {
        selected() {
            const thing = this.rankees
                .filter(r => r.rank)
                .sort((a, b) => a.rank - b.rank);
            return thing;
        },
        unselected() {
            return this.rankees.filter(r => !r.rank);
        }
    },
    methods: {
        select(option, i) {
            const index = _.indexOf(this.rankees, option);
            this.rankees[index].rank = Math.max(
                Math.max(...this.rankees.map(r => r.rank ?? 0), 0) + 1
            );
        },
        up(option, i) {
            if (i === 0) {
                return;
            }
            const newState = this.rankees.slice();
            const me = _.findIndex(this.rankees, option);
            const aboveMe = _.findIndex(this.rankees, {
                rank: newState[me].rank - 1
            });
            newState[me].rank = newState[me].rank - 1;
            newState[aboveMe].rank = newState[aboveMe].rank + 1;
            this.rankees = newState;
        },
        down(option, i) {
            if (i === this.rankees.length - 1) {
                return;
            }
            const newState = this.rankees.slice();
            const me = _.findIndex(this.rankees, option);
            const aboveMe = _.findIndex(this.rankees, {
                rank: newState[me].rank + 1
            });
            newState[me].rank = newState[me].rank + 1;
            newState[aboveMe].rank = newState[aboveMe].rank - 1;
            this.rankees = newState;
        }
    }
};
</script>
<style lang="scss">
.rank {
    max-width: 30px;
}
</style>
