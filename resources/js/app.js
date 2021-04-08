require("./bootstrap");
import Vue from "vue";

Vue.component(
    "rankedchoice",
    require("../../app/BallotComponents/RankedChoice/v1/form.vue").default
);

new Vue({
    el: "#app"
});
