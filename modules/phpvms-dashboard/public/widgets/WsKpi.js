import { t as e } from "./_plugin-vue_export-helper.js";
import { createCommentVNode as t, createElementBlock as n, createElementVNode as r, createTextVNode as i, defineComponent as a, openBlock as o, toDisplayString as s } from "vue";
//#region ../../../../modules/phpvms-dashboard/ui/WsKpi.vue?vue&type=script&setup=true&lang.ts
var c = { class: "kpi" }, l = { class: "val tnum" }, u = {
	key: 0,
	class: "unit"
}, d = {
	key: 0,
	class: "sub"
}, f = /*#__PURE__*/ e(/* @__PURE__ */ a({
	__name: "WsKpi",
	props: {
		value: {},
		unit: { default: "" },
		sub: { default: "" }
	},
	setup(e) {
		return (a, f) => (o(), n("div", c, [r("div", l, [i(s(e.value), 1), e.unit ? (o(), n("span", u, s(e.unit), 1)) : t("", !0)]), e.sub ? (o(), n("div", d, s(e.sub), 1)) : t("", !0)]));
	}
}), [["__scopeId", "data-v-eccace85"]]);
//#endregion
export { f as t };
