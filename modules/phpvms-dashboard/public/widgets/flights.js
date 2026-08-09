import { t as e } from "./WsKpi.js";
import { createBlock as t, defineComponent as n, openBlock as r } from "vue";
//#endregion
//#region ../../../../modules/phpvms-dashboard/ui/FlightsWidget.vue
var i = /* @__PURE__ */ n({
	__name: "FlightsWidget",
	props: { value: {} },
	setup(n) {
		return (i, a) => (r(), t(e, {
			value: n.value ?? 0,
			sub: "Accepted flights"
		}, null, 8, ["value"]));
	}
});
//#endregion
export { i as default };
