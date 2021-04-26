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
        },
        remove(option, i) {
            const newState = this.rankees.slice();
            const index = _.indexOf(newState, option);
            const rank = newState[index].rank;
            for (const rankee of newState) {
                if (rankee.rank > rank) {
                    rankee.rank -= 1;
                }
            }
            newState[index].rank = undefined;
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
