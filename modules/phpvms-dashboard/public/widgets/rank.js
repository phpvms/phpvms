import { defineComponent as d, computed as l, openBlock as a, createElementBlock as e, createElementVNode as o, toDisplayString as n, createTextVNode as k, normalizeStyle as m } from "vue";
import { _ as p } from "./_plugin-vue_export-helper.js";
const _ = {
  key: 0,
  class: "rank"
}, h = { class: "row" }, f = { class: "from" }, u = {
  key: 0,
  class: "to"
}, v = {
  key: 1,
  class: "to top"
}, y = { class: "track" }, x = { class: "pct tnum" }, g = {
  key: 1,
  class: "empty"
}, M = /* @__PURE__ */ d({
  __name: "RankWidget",
  props: {
    rank: {}
  },
  setup(t) {
    const i = t, c = l(() => {
      var s;
      return Math.max(0, Math.min(100, Math.round(((s = i.rank) == null ? void 0 : s.pct) ?? 0)));
    });
    return (s, r) => t.rank ? (a(), e("div", _, [
      o("div", h, [
        o("span", f, n(t.rank.from), 1),
        t.rank.to ? (a(), e("span", u, [
          r[0] || (r[0] = o("span", { class: "arrow" }, "→", -1)),
          k(" " + n(t.rank.to), 1)
        ])) : (a(), e("span", v, "Top rank"))
      ]),
      o("div", y, [
        o("div", {
          class: "fill",
          style: m({ width: t.rank.to ? `${c.value}%` : "100%" })
        }, null, 4)
      ]),
      o("div", x, n(t.rank.to ? `${c.value}% to promotion` : "Max rank reached"), 1)
    ])) : (a(), e("div", g, "No rank assigned"));
  }
}), B = /* @__PURE__ */ p(M, [["__scopeId", "data-v-e619fe81"]]);
export {
  B as default
};
