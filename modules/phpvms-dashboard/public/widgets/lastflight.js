import { t as e } from "./_plugin-vue_export-helper.js";
import { computed as t, createCommentVNode as n, createElementBlock as r, createElementVNode as i, createTextVNode as a, defineComponent as o, openBlock as s, toDisplayString as c } from "vue";
//#region ../../../../modules/phpvms-dashboard/ui/LastFlightWidget.vue?vue&type=script&setup=true&lang.ts
var l = {
	key: 0,
	class: "lf"
}, u = { class: "top" }, d = { class: "cs" }, f = {
	key: 0,
	class: "badge"
}, p = { class: "route tnum" }, m = { class: "meta" }, h = { class: "tnum" }, g = {
	key: 1,
	class: "empty"
}, _ = /*#__PURE__*/ e(/* @__PURE__ */ o({
	__name: "LastFlightWidget",
	props: { pirep: {} },
	setup(e) {
		let o = e, _ = t(() => {
			let e = o.pirep?.flight_time;
			return e == null ? "—" : `${String(Math.floor(e / 60)).padStart(2, "0")}:${String(e % 60).padStart(2, "0")}`;
		});
		return (t, o) => e.pirep ? (s(), r("div", l, [
			i("div", u, [i("span", d, c(e.pirep.ident ?? "PIREP"), 1), e.pirep.state ? (s(), r("span", f, c(e.pirep.state.label), 1)) : n("", !0)]),
			i("div", p, [
				a(c(e.pirep.dpt_airport?.icao ?? "—") + " ", 1),
				o[0] ||= i("span", { class: "arrow" }, "→", -1),
				a(" " + c(e.pirep.arr_airport?.icao ?? "—"), 1)
			]),
			i("dl", m, [i("div", null, [o[1] ||= i("dt", null, "Block", -1), i("dd", h, c(_.value), 1)]), i("div", null, [o[2] ||= i("dt", null, "Aircraft", -1), i("dd", null, c(e.pirep.aircraft?.registration ?? "—"), 1)])])
		])) : (s(), r("div", g, "No flights logged yet"));
	}
}), [["__scopeId", "data-v-0f3af5e8"]]);
//#endregion
export { _ as default };
