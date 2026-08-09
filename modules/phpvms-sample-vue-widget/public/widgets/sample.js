(function(){try{if(typeof document<`u`){var e=document.createElement(`style`);e.appendChild(document.createTextNode(`.svw[data-v-703e69ff]{flex-direction:column;gap:10px;display:flex}.svw-head[data-v-703e69ff]{justify-content:space-between;align-items:baseline;display:flex}.svw-label[data-v-703e69ff]{color:var(--pv-accent,#4f8cff);font-size:13px;font-weight:500}.svw-refresh[data-v-703e69ff]{cursor:pointer;color:var(--pv-ink-dim,#8a94a6);background:0 0;border:none;padding:0 2px;font-size:13px;line-height:1}.svw-refresh[data-v-703e69ff]:hover{color:var(--pv-accent,#4f8cff)}.svw-loading[data-v-703e69ff]{color:var(--pv-ink-dim,#8a94a6);align-items:center;gap:8px;font-size:12px;display:flex}.svw-spin[data-v-703e69ff]{border:2px solid var(--pv-line,#2a2f3a);border-top-color:var(--pv-accent,#4f8cff);border-radius:50%;width:12px;height:12px;animation:.8s linear infinite svw-spin-703e69ff}@keyframes svw-spin-703e69ff{to{transform:rotate(360deg)}}.svw-rows[data-v-703e69ff]{flex-direction:column;gap:4px;margin:0;display:flex}.svw-rows div[data-v-703e69ff]{gap:10px;display:flex}.svw-rows dt[data-v-703e69ff]{text-transform:uppercase;letter-spacing:.05em;min-width:62px;color:var(--pv-ink-faint,#6b7280);font-size:11px}.svw-rows dd[data-v-703e69ff]{color:var(--pv-ink,#e6e9ef);margin:0;font-size:13px}.mono[data-v-703e69ff]{font-family:var(--pv-font-mono,ui-monospace, monospace)}.svw-err[data-v-703e69ff]{color:var(--pv-slot-error-text,#ffb4b4);background:var(--pv-slot-error-bg,#ff505014);border:1px solid var(--pv-slot-error-border,#ff50504d);border-radius:var(--pv-radius-sm,6px);padding:8px 10px;font-size:12px}/*$vite$:1*/`)),document.head.appendChild(e)}}catch(e){console.error(`vite-plugin-css-injected-by-js`,e)}})();
import { createElementBlock as e, createElementVNode as t, createTextVNode as n, defineComponent as r, onBeforeUnmount as i, onMounted as a, openBlock as o, ref as s, toDisplayString as c } from "vue";
//#region ../../../../modules/phpvms-sample-vue-widget/ui/SampleVueWidget.vue?vue&type=script&setup=true&lang.ts
var l = { class: "svw" }, u = { class: "svw-head" }, d = { class: "svw-label" }, f = {
	key: 0,
	class: "svw-loading",
	role: "status"
}, p = {
	key: 1,
	class: "svw-rows"
}, m = { class: "mono" }, h = { class: "mono" }, g = {
	key: 2,
	class: "svw-err",
	role: "alert",
	"data-sample-vue-error": ""
}, _ = /*#__PURE__*/ ((e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
})(/* @__PURE__ */ r({
	__name: "SampleVueWidget",
	props: { label: { default: "Sample Vue widget" } },
	setup(r) {
		let _ = r, v = s({ status: "loading" }), y = null;
		async function b() {
			y?.abort(), y = new AbortController(), v.value = { status: "loading" };
			try {
				let e = await fetch("/api/sample-vue/ping", {
					signal: y.signal,
					credentials: "same-origin",
					headers: {
						Accept: "application/json",
						"X-Requested-With": "XMLHttpRequest"
					}
				}), t = await e.json();
				if (!e.ok || t && "error" in t) {
					v.value = {
						status: "error",
						message: t?.message ?? `Unavailable (HTTP ${e.status})`
					};
					return;
				}
				v.value = {
					status: "success",
					data: t
				};
			} catch (e) {
				if (e instanceof DOMException && e.name === "AbortError") return;
				v.value = {
					status: "error",
					message: e instanceof Error ? e.message : "Fetch error"
				};
			}
		}
		return a(b), i(() => y?.abort()), (r, i) => (o(), e("div", l, [t("div", u, [t("span", d, c(_.label), 1), t("button", {
			class: "svw-refresh",
			type: "button",
			title: "Reload",
			onClick: b
		}, "↻")]), v.value.status === "loading" ? (o(), e("div", f, [...i[0] ||= [t("span", {
			class: "svw-spin",
			"aria-hidden": "true"
		}, null, -1), n(" Loading… ", -1)]])) : v.value.status === "success" ? (o(), e("dl", p, [
			t("div", null, [i[1] ||= t("dt", null, "Addon", -1), t("dd", m, c(v.value.data.addon), 1)]),
			t("div", null, [i[2] ||= t("dt", null, "Message", -1), t("dd", null, c(v.value.data.message), 1)]),
			t("div", null, [i[3] ||= t("dt", null, "Time", -1), t("dd", h, c(v.value.data.time), 1)])
		])) : (o(), e("div", g, c(v.value.message), 1))]));
	}
}), [["__scopeId", "data-v-703e69ff"]]);
//#endregion
export { _ as default };
