import { defineComponent as m, computed as f, openBlock as r, createElementBlock as a, createElementVNode as t, toDisplayString as i, createCommentVNode as g, createTextVNode as c } from "vue";
import { _ } from "./_plugin-vue_export-helper.js";
const h = {
  key: 0,
  class: "lf"
}, v = { class: "top" }, k = { class: "cs" }, y = {
  key: 0,
  class: "badge"
}, S = { class: "route tnum" }, x = { class: "meta" }, N = { class: "tnum" }, b = {
  key: 1,
  class: "empty"
}, B = /* @__PURE__ */ m({
  __name: "LastFlightWidget",
  props: {
    pirep: {}
  },
  setup(s) {
    const p = s, u = f(() => {
      var e;
      const o = (e = p.pirep) == null ? void 0 : e.flight_time;
      return o == null ? "—" : `${String(Math.floor(o / 60)).padStart(2, "0")}:${String(o % 60).padStart(2, "0")}`;
    });
    return (o, e) => {
      var n, l, d;
      return s.pirep ? (r(), a("div", h, [
        t("div", v, [
          t("span", k, i(s.pirep.ident ?? "PIREP"), 1),
          s.pirep.state ? (r(), a("span", y, i(s.pirep.state.label), 1)) : g("", !0)
        ]),
        t("div", S, [
          c(i(((n = s.pirep.dpt_airport) == null ? void 0 : n.icao) ?? "—") + " ", 1),
          e[0] || (e[0] = t("span", { class: "arrow" }, "→", -1)),
          c(" " + i(((l = s.pirep.arr_airport) == null ? void 0 : l.icao) ?? "—"), 1)
        ]),
        t("dl", x, [
          t("div", null, [
            e[1] || (e[1] = t("dt", null, "Block", -1)),
            t("dd", N, i(u.value), 1)
          ]),
          t("div", null, [
            e[2] || (e[2] = t("dt", null, "Aircraft", -1)),
            t("dd", null, i(((d = s.pirep.aircraft) == null ? void 0 : d.registration) ?? "—"), 1)
          ])
        ])
      ])) : (r(), a("div", b, "No flights logged yet"));
    };
  }
}), C = /* @__PURE__ */ _(B, [["__scopeId", "data-v-d4ea3073"]]);
export {
  C as default
};
