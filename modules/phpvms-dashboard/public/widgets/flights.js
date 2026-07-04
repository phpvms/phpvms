import { defineComponent as t, openBlock as o, createBlock as a } from "vue";
import { W as l } from "./WsKpi.js";
const s = /* @__PURE__ */ t({
  __name: "FlightsWidget",
  props: {
    value: {}
  },
  setup(e) {
    return (r, c) => (o(), a(l, {
      value: e.value ?? 0,
      sub: "Accepted flights"
    }, null, 8, ["value"]));
  }
});
export {
  s as default
};
