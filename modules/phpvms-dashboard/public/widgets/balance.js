import { t as e } from "./WsKpi.js";
import { computed as t, createBlock as n, defineComponent as r, openBlock as i } from "vue";
//#endregion
//#region ../../../../modules/phpvms-dashboard/ui/BalanceWidget.vue
var a = /* @__PURE__ */ r({
	__name: "BalanceWidget",
	props: { balance: {} },
	setup(r) {
		let a = r, o = t(() => a.balance?.formatted ?? "—");
		return (t, r) => (i(), n(e, {
			value: o.value,
			sub: "Account balance"
		}, null, 8, ["value"]));
	}
});
//#endregion
export { a as default };
