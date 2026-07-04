(function(){"use strict";try{if(typeof document<"u"){var e=document.createElement("style");e.appendChild(document.createTextNode(".svw[data-v-8966c39e]{display:flex;flex-direction:column;gap:10px}.svw-head[data-v-8966c39e]{display:flex;align-items:baseline;justify-content:space-between}.svw-label[data-v-8966c39e]{font-size:13px;font-weight:500;color:var(--pv-accent, #4f8cff)}.svw-refresh[data-v-8966c39e]{background:none;border:none;cursor:pointer;padding:0 2px;font-size:13px;line-height:1;color:var(--pv-ink-dim, #8a94a6)}.svw-refresh[data-v-8966c39e]:hover{color:var(--pv-accent, #4f8cff)}.svw-loading[data-v-8966c39e]{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--pv-ink-dim, #8a94a6)}.svw-spin[data-v-8966c39e]{width:12px;height:12px;border:2px solid var(--pv-line, #2a2f3a);border-top-color:var(--pv-accent, #4f8cff);border-radius:50%;animation:svw-spin-8966c39e .8s linear infinite}@keyframes svw-spin-8966c39e{to{transform:rotate(360deg)}}.svw-rows[data-v-8966c39e]{display:flex;flex-direction:column;gap:4px;margin:0}.svw-rows div[data-v-8966c39e]{display:flex;gap:10px}.svw-rows dt[data-v-8966c39e]{font-size:11px;text-transform:uppercase;letter-spacing:.05em;min-width:62px;color:var(--pv-ink-faint, #6b7280)}.svw-rows dd[data-v-8966c39e]{margin:0;font-size:13px;color:var(--pv-ink, #e6e9ef)}.mono[data-v-8966c39e]{font-family:var(--pv-font-mono, ui-monospace, monospace)}.svw-err[data-v-8966c39e]{font-size:12px;padding:8px 10px;color:var(--pv-slot-error-text, #ffb4b4);background:var(--pv-slot-error-bg, rgba(255, 80, 80, .08));border:1px solid var(--pv-slot-error-border, rgba(255, 80, 80, .3));border-radius:var(--pv-radius-sm, 6px)}")),document.head.appendChild(e)}}catch(a){console.error("vite-plugin-css-injected-by-js",a)}})();
import { defineComponent as c, ref as v, onMounted as p, onBeforeUnmount as m, openBlock as d, createElementBlock as i, createElementVNode as t, toDisplayString as n, createTextVNode as g } from "vue";
const _ = { class: "svw" }, f = { class: "svw-head" }, w = { class: "svw-label" }, b = {
  key: 0,
  class: "svw-loading",
  role: "status"
}, h = {
  key: 1,
  class: "svw-rows"
}, k = { class: "mono" }, y = { class: "mono" }, x = {
  key: 2,
  class: "svw-err",
  role: "alert",
  "data-sample-vue-error": ""
}, E = /* @__PURE__ */ c({
  __name: "SampleVueWidget",
  props: {
    label: { default: "Sample Vue widget" }
  },
  setup(l) {
    const u = l, s = v({ status: "loading" });
    let a = null;
    async function r() {
      a == null || a.abort(), a = new AbortController(), s.value = { status: "loading" };
      try {
        const o = await fetch("/api/sample-vue/ping", {
          signal: a.signal,
          credentials: "same-origin",
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
        }), e = await o.json();
        if (!o.ok || e && "error" in e) {
          s.value = {
            status: "error",
            message: (e == null ? void 0 : e.message) ?? `Unavailable (HTTP ${o.status})`
          };
          return;
        }
        s.value = { status: "success", data: e };
      } catch (o) {
        if (o instanceof DOMException && o.name === "AbortError") return;
        s.value = {
          status: "error",
          message: o instanceof Error ? o.message : "Fetch error"
        };
      }
    }
    return p(r), m(() => a == null ? void 0 : a.abort()), (o, e) => (d(), i("div", _, [
      t("div", f, [
        t("span", w, n(u.label), 1),
        t("button", {
          class: "svw-refresh",
          type: "button",
          title: "Reload",
          onClick: r
        }, "↻")
      ]),
      s.value.status === "loading" ? (d(), i("div", b, [...e[0] || (e[0] = [
        t("span", {
          class: "svw-spin",
          "aria-hidden": "true"
        }, null, -1),
        g(" Loading… ", -1)
      ])])) : s.value.status === "success" ? (d(), i("dl", h, [
        t("div", null, [
          e[1] || (e[1] = t("dt", null, "Addon", -1)),
          t("dd", k, n(s.value.data.addon), 1)
        ]),
        t("div", null, [
          e[2] || (e[2] = t("dt", null, "Message", -1)),
          t("dd", null, n(s.value.data.message), 1)
        ]),
        t("div", null, [
          e[3] || (e[3] = t("dt", null, "Time", -1)),
          t("dd", y, n(s.value.data.time), 1)
        ])
      ])) : (d(), i("div", x, n(s.value.message), 1))
    ]));
  }
}), V = (l, u) => {
  const s = l.__vccOpts || l;
  for (const [a, r] of u)
    s[a] = r;
  return s;
}, M = /* @__PURE__ */ V(E, [["__scopeId", "data-v-8966c39e"]]);
export {
  M as default
};
