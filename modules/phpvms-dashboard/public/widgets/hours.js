import { t as e } from "./WsKpi.js";
import { createBlock as t, defineComponent as n, openBlock as r } from "vue";
//#endregion
//#region ../../../../modules/phpvms-dashboard/ui/HoursWidget.vue
var i = /* @__PURE__ */ n({
	__name: "HoursWidget",
	props: { value: {} },
	setup(n) {
		return (i, a) => (r(), t(e, {
			value: n.value ?? "—",
			sub: "Total flight time"
		}, null, 8, ["value"]));
	}
});
//#endregion
export { i as default };
