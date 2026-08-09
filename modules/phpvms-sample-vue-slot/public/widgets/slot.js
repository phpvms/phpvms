(function(){try{if(typeof document<`u`){var e=document.createElement(`style`);e.appendChild(document.createTextNode(`.sbs[data-v-597a1596]:hover{border-color:var(--pv-accent,#4f8cff);color:var(--pv-accent,#4f8cff)}.sbs-ident[data-v-597a1596]{opacity:.85}/*$vite$:1*/`)),document.head.appendChild(e)}}catch(e){console.error(`vite-plugin-css-injected-by-js`,e)}})();
import { computed as e, createElementBlock as t, createElementVNode as n, defineComponent as r, normalizeStyle as i, openBlock as a, ref as o, toDisplayString as s } from "vue";
//#region ../../../../modules/phpvms-sample-vue-slot/ui/SampleBidsSlot.vue?vue&type=script&setup=true&lang.ts
var c = ["data-connected", "title"], l = { "aria-hidden": "true" }, u = {
	class: "sbs-ident",
	style: { fontFamily: "var(--pv-font-mono, ui-monospace, monospace)" }
}, d = /*#__PURE__*/ ((e, t) => {
	let n = e.__vccOpts || e;
	for (let [e, r] of t) n[e] = r;
	return n;
})(/* @__PURE__ */ r({
	__name: "SampleBidsSlot",
	props: {
		bid: {},
		flight: {}
	},
	setup(r) {
		let d = r, f = e(() => {
			let e = d.flight ?? {};
			return e.callsign ?? e.ident ?? e.flightId ?? "—";
		}), p = o(!1);
		function m() {
			p.value = !p.value;
		}
		return (e, r) => (a(), t("button", {
			type: "button",
			class: "sbs",
			"data-connected": p.value ? "true" : "false",
			title: p.value ? `ACARS connected — ${f.value}` : `Connect ACARS for ${f.value}`,
			style: i({
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
				border: `1px solid ${p.value ? "var(--pv-accent, #4f8cff)" : "var(--pv-line, #2a2f3a)"}`,
				color: p.value ? "var(--pv-accent, #4f8cff)" : "var(--pv-ink-dim, #8a94a6)",
				background: p.value ? "var(--pv-accent-soft, rgba(79, 140, 255, 0.12))" : "transparent"
			}),
			onClick: m
		}, [
			n("span", l, s(p.value ? "◉" : "◯"), 1),
			r[0] ||= n("span", null, "ACARS", -1),
			n("span", u, s(f.value), 1)
		], 12, c));
	}
}), [["__scopeId", "data-v-597a1596"]]);
//#endregion
export { d as default };
