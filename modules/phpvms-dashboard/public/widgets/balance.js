import { defineComponent as c, computed as n, openBlock as l, createBlock as r } from "vue";
import { W as p } from "./WsKpi.js";
const f = /* @__PURE__ */ c({
  __name: "BalanceWidget",
  props: {
    balance: {}
  },
  setup(a) {
    const o = a, t = n(() => {
      var e;
      return ((e = o.balance) == null ? void 0 : e.formatted) ?? "—";
    });
    return (e, u) => (l(), r(p, {
      value: t.value,
      sub: "Account balance"
    }, null, 8, ["value"]));
  }
});
export {
  f as default
};
