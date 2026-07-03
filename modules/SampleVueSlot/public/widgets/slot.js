(function(){"use strict";try{if(typeof document<"u"){var e=document.createElement("style");e.appendChild(document.createTextNode(".sbs[data-v-155a7223]:hover{border-color:var(--pv-accent, #4f8cff);color:var(--pv-accent, #4f8cff)}.sbs-ident[data-v-155a7223]{opacity:.85}")),document.head.appendChild(e)}}catch(c){console.error("vite-plugin-css-injected-by-js",c)}})();
import { defineComponent as i, computed as p, ref as d, openBlock as u, createElementBlock as f, normalizeStyle as v, createElementVNode as l, toDisplayString as r } from "vue";
const m = ["data-connected", "title"], _ = { "aria-hidden": "true" }, g = {
  class: "sbs-ident",
  style: { fontFamily: "var(--pv-font-mono, ui-monospace, monospace)" }
}, S = /* @__PURE__ */ i({
  __name: "SampleBidsSlot",
  props: {
    bid: {},
    flight: {}
  },
  setup(n) {
    const o = n, t = p(() => {
      const a = o.flight ?? {};
      return a.callsign ?? a.ident ?? a.flightId ?? "—";
    }), e = d(!1);
    function s() {
      e.value = !e.value;
    }
    return (a, c) => (u(), f("button", {
      type: "button",
      class: "sbs",
      "data-connected": e.value ? "true" : "false",
      title: e.value ? `ACARS connected — ${t.value}` : `Connect ACARS for ${t.value}`,
      style: v({
        display: "inline-flex",
        alignItems: "center",
        gap: "5px",
        padding: "2px 8px",
        fontSize: "11px",
        lineHeight: "1.4",
        fontWeight: "500",
        cursor: "pointer",
        whiteSpace: "nowrap",
        borderRadius: "var(--pv-radius-sm, 6px)",
        border: `1px solid ${e.value ? "var(--pv-accent, #4f8cff)" : "var(--pv-line, #2a2f3a)"}`,
        color: e.value ? "var(--pv-accent, #4f8cff)" : "var(--pv-ink-dim, #8a94a6)",
        background: e.value ? "var(--pv-accent-soft, rgba(79, 140, 255, 0.12))" : "transparent"
      }),
      onClick: s
    }, [
      l("span", _, r(e.value ? "◉" : "◯"), 1),
      c[0] || (c[0] = l("span", null, "ACARS", -1)),
      l("span", g, r(t.value), 1)
    ], 12, m));
  }
}), x = (n, o) => {
  const t = n.__vccOpts || n;
  for (const [e, s] of o)
    t[e] = s;
  return t;
}, h = /* @__PURE__ */ x(S, [["__scopeId", "data-v-155a7223"]]);
export {
  h as default
};
