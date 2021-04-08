require("./bootstrap");
import Vue from "vue";
window.Vue = Vue;

Vue.component(
    "rankedchoice",
    require("../../app/BallotComponents/RankedChoice/v1/form.vue").default
);

new Vue({
    el: "#app"
});
