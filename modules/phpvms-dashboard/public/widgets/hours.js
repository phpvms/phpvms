import { defineComponent as t, openBlock as o, createBlock as a } from "vue";
import { W as r } from "./WsKpi.js";
const s = /* @__PURE__ */ t({
  __name: "HoursWidget",
  props: {
    value: {}
  },
  setup(e) {
    return (l, u) => (o(), a(r, {
      value: e.value ?? "—",
      sub: "Total flight time"
    }, null, 8, ["value"]));
  }
});
export {
  s as default
};
