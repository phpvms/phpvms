(function(){"use strict";try{if(typeof document<"u"){var a=document.createElement("style");a.appendChild(document.createTextNode(".wx[data-v-42966a68]{display:flex;flex-direction:column;gap:10px}.wxid[data-v-42966a68]{display:flex;align-items:baseline;justify-content:space-between}.micro[data-v-42966a68]{font-size:11px;color:var(--pv-ink-faint, #6b7280);text-transform:uppercase;letter-spacing:.05em}.code[data-v-42966a68]{font-family:var(--pv-font-mono, ui-monospace, monospace);font-size:14px;color:var(--pv-accent, #4f8cff);font-weight:500}.loading[data-v-42966a68]{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--pv-ink-dim, #8a94a6)}.spin[data-v-42966a68]{width:12px;height:12px;border:2px solid var(--pv-line, #2a2f3a);border-top-color:var(--pv-accent, #4f8cff);border-radius:50%;animation:spin-42966a68 .8s linear infinite}@keyframes spin-42966a68{to{transform:rotate(360deg)}}.rows[data-v-42966a68]{display:flex;flex-direction:column;gap:4px;margin:0}.rows div[data-v-42966a68]{display:flex;gap:10px}.rows dt[data-v-42966a68]{font-size:11px;color:var(--pv-ink-faint, #6b7280);text-transform:uppercase;letter-spacing:.05em;min-width:46px}.rows dd[data-v-42966a68]{margin:0;font-size:13px;color:var(--pv-ink, #e6e9ef)}.metar[data-v-42966a68]{font-family:var(--pv-font-mono, ui-monospace, monospace);font-size:11px;color:var(--pv-ink-dim, #8a94a6);background:var(--pv-panel-inset, rgba(0, 0, 0, .04));border-radius:var(--pv-radius-sm, 6px);padding:8px 10px;white-space:pre-wrap;word-break:break-word}.err[data-v-42966a68]{font-size:12px;color:var(--pv-slot-error-text, #ffb4b4);background:var(--pv-slot-error-bg, rgba(255, 80, 80, .08));border:1px solid var(--pv-slot-error-border, rgba(255, 80, 80, .3));border-radius:var(--pv-radius-sm, 6px);padding:8px 10px}")),document.head.appendChild(a)}}catch(e){console.error("vite-plugin-css-injected-by-js",e)}})();
import { defineComponent as v, ref as p, onMounted as _, watch as g, onBeforeUnmount as h, openBlock as o, createElementBlock as n, createElementVNode as s, toDisplayString as r, createTextVNode as f, Fragment as k, createCommentVNode as l } from "vue";
const w = { class: "wx" }, y = { class: "wxid" }, b = { class: "code tnum" }, x = {
  key: 0,
  class: "loading",
  role: "status"
}, C = { class: "rows" }, W = { key: 0 }, E = { key: 1 }, A = { class: "tnum" }, N = { key: 2 }, T = { class: "tnum" }, B = {
  key: 0,
  class: "metar"
}, I = {
  key: 2,
  class: "err",
  role: "alert",
  "data-weather-error": ""
}, M = /* @__PURE__ */ v({
  __name: "WeatherWidget",
  props: {
    icao: {}
  },
  setup(u) {
    const d = u, t = p({ status: "idle" });
    let a = null;
    async function c() {
      const m = (d.icao ?? "").trim();
      if (!m) {
        t.value = { status: "error", message: "No ICAO available." };
        return;
      }
      a == null || a.abort(), a = new AbortController(), t.value = { status: "loading" };
      try {
        const e = await fetch(`/api/phpvms-dashboard/weather/${encodeURIComponent(m)}`, {
          signal: a.signal,
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin"
        }), i = await e.json();
        if (!e.ok || "error" in i) {
          t.value = { status: "error", message: (i == null ? void 0 : i.message) ?? `Unavailable (HTTP ${e.status})` };
          return;
        }
        t.value = { status: "success", data: i };
      } catch (e) {
        if (e instanceof DOMException && e.name === "AbortError") return;
        t.value = { status: "error", message: e instanceof Error ? e.message : "Fetch error" };
      }
    }
    return _(c), g(() => d.icao, c), h(() => a == null ? void 0 : a.abort()), (m, e) => (o(), n("div", w, [
      s("div", y, [
        e[0] || (e[0] = s("span", { class: "micro" }, "Station", -1)),
        s("span", b, r(d.icao ?? "—"), 1)
      ]),
      t.value.status === "loading" || t.value.status === "idle" ? (o(), n("div", x, [...e[1] || (e[1] = [
        s("span", {
          class: "spin",
          "aria-hidden": "true"
        }, null, -1),
        f(" Loading… ", -1)
      ])])) : t.value.status === "success" ? (o(), n(k, { key: 1 }, [
        s("dl", C, [
          t.value.data.conditions ? (o(), n("div", W, [
            e[2] || (e[2] = s("dt", null, "Cond", -1)),
            s("dd", null, r(t.value.data.conditions), 1)
          ])) : l("", !0),
          t.value.data.temperature ? (o(), n("div", E, [
            e[3] || (e[3] = s("dt", null, "Temp", -1)),
            s("dd", A, r(t.value.data.temperature), 1)
          ])) : l("", !0),
          t.value.data.wind ? (o(), n("div", N, [
            e[4] || (e[4] = s("dt", null, "Wind", -1)),
            s("dd", T, r(t.value.data.wind), 1)
          ])) : l("", !0)
        ]),
        t.value.data.metar ? (o(), n("pre", B, r(t.value.data.metar), 1)) : l("", !0)
      ], 64)) : t.value.status === "error" ? (o(), n("div", I, r(t.value.message), 1)) : l("", !0)
    ]));
  }
}), O = (u, d) => {
  const t = u.__vccOpts || u;
  for (const [a, c] of d)
    t[a] = c;
  return t;
}, U = /* @__PURE__ */ O(M, [["__scopeId", "data-v-42966a68"]]);
export {
  U as default
};
