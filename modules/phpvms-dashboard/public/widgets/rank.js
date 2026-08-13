import { t as e } from "./_plugin-vue_export-helper.js";
import { computed as t, createElementBlock as n, createElementVNode as r, createTextVNode as i, defineComponent as a, normalizeStyle as o, openBlock as s, toDisplayString as c } from "vue";
//#region ../../../../modules/phpvms-dashboard/ui/RankWidget.vue?vue&type=script&setup=true&lang.ts
var l = {
	key: 0,
	class: "rank"
}, u = { class: "row" }, d = { class: "from" }, f = {
	key: 0,
	class: "to"
}, p = {
	key: 1,
	class: "to top"
}, m = { class: "track" }, h = { class: "pct tnum" }, g = {
	key: 1,
	class: "empty"
}, _ = /*#__PURE__*/ e(/* @__PURE__ */ a({
	__name: "RankWidget",
	props: { rank: {} },
	setup(e) {
		let a = e, _ = t(() => Math.max(0, Math.min(100, Math.round(a.rank?.pct ?? 0))));
		return (t, a) => e.rank ? (s(), n("div", l, [
			r("div", u, [r("span", d, c(e.rank.from), 1), e.rank.to ? (s(), n("span", f, [a[0] ||= r("span", { class: "arrow" }, "→", -1), i(" " + c(e.rank.to), 1)])) : (s(), n("span", p, "Top rank"))]),
			r("div", m, [r("div", {
				class: "fill",
				style: o({ width: e.rank.to ? `${_.value}%` : "100%" })
			}, null, 4)]),
			r("div", h, c(e.rank.to ? `${_.value}% to promotion` : "Max rank reached"), 1)
		])) : (s(), n("div", g, "No rank assigned"));
	}
}), [["__scopeId", "data-v-f601398a"]]);
//#endregion
export { _ as default };
