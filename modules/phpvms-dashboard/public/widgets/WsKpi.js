import { defineComponent as a, openBlock as t, createElementBlock as s, createElementVNode as c, createTextVNode as i, toDisplayString as n, createCommentVNode as o } from "vue";
import { _ as u } from "./_plugin-vue_export-helper.js";
const d = { class: "kpi" }, l = { class: "val tnum" }, r = {
  key: 0,
  class: "unit"
}, m = {
  key: 0,
  class: "sub"
}, _ = /* @__PURE__ */ a({
  __name: "WsKpi",
  props: {
    value: {},
    unit: { default: "" },
    sub: { default: "" }
  },
  setup(e) {
    return (f, p) => (t(), s("div", d, [
      c("div", l, [
        i(n(e.value), 1),
        e.unit ? (t(), s("span", r, n(e.unit), 1)) : o("", !0)
      ]),
      e.sub ? (t(), s("div", m, n(e.sub), 1)) : o("", !0)
    ]));
  }
}), k = /* @__PURE__ */ u(_, [["__scopeId", "data-v-f35da426"]]);
export {
  k as W
};
