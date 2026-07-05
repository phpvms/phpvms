import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import PvSlot from "@/shared/ui/PvSlot.vue";
import { RegistryKey, ResolverKey, PagePropsKey } from "@/shared/lib/usePvSlot";
import type { SlotEntry, ComponentResolver } from "@/shared/lib/registry";

const Widget = defineComponent({
  props: { icao: { type: String, default: "" }, tag: { type: String, default: "" } },
  setup: (p) => () => h("div", { class: "w", "data-tag": p.tag }, p.icao),
});

function mountSlot(
  registry: SlotEntry[],
  resolver: ComponentResolver,
  pageProps: Record<string, unknown> = {},
  slotProps: Record<string, unknown> = {},
) {
  return mount(PvSlot, {
    props: { name: "dashboard.sidebar", ...slotProps },
    global: {
      provide: {
        [RegistryKey as symbol]: registry,
        [ResolverKey as symbol]: resolver,
        [PagePropsKey as symbol]: pageProps,
      },
    },
  });
}

describe("PvSlot", () => {
  it("renders registered components in ascending order with resolved @-ref props", () => {
    const registry: SlotEntry[] = [
      { slot: "dashboard.sidebar", component: "Widget", order: 20, props: { tag: "second" } },
      {
        slot: "dashboard.sidebar",
        component: "Widget",
        order: 10,
        props: { icao: "@currentAirport", tag: "first" },
      },
    ];
    const w = mountSlot(registry, { Widget }, { currentAirport: "KJFK" });
    const tags = w.findAll(".w").map((el) => el.attributes("data-tag"));
    expect(tags).toEqual(["first", "second"]);
    expect(w.find('[data-tag="first"]').text()).toBe("KJFK");
  });

  it("renders a fail-visible diagnostic when the component name is unresolved", () => {
    const registry: SlotEntry[] = [{ slot: "dashboard.sidebar", component: "Ghost", order: 1 }];
    const w = mountSlot(registry, {});
    expect(w.find('[data-pv-slot-failed="Ghost"]').exists()).toBe(true);
    expect(w.find(".w").exists()).toBe(false);
  });

  it("renders nothing for an empty slot", () => {
    const w = mountSlot([], { Widget });
    expect(w.find(".w").exists()).toBe(false);
    expect(w.find('[role="alert"]').exists()).toBe(false);
  });

  it("resolves a @-ref from per-instance context when absent from page props", () => {
    const RowWidget = defineComponent({
      props: { bid: { type: Object, default: () => ({}) } },
      setup: (p) => () => h("div", { class: "row", "data-bid": (p.bid as { id?: string }).id }),
    });
    const registry: SlotEntry[] = [
      { slot: "dashboard.sidebar", component: "RowWidget", order: 1, props: { bid: "@bid" } },
    ];
    // @bid lives only in context (the row), not in the page DTO.
    const w = mountSlot(registry, { RowWidget }, {}, { context: { bid: { id: "B42" } } });
    expect(w.find(".row").attributes("data-bid")).toBe("B42");
  });

  it("lets context override a page-prop of the same name", () => {
    const registry: SlotEntry[] = [
      {
        slot: "dashboard.sidebar",
        component: "Widget",
        order: 1,
        props: { icao: "@currentAirport" },
      },
    ];
    const w = mountSlot(
      registry,
      { Widget },
      { currentAirport: "KJFK" },
      { context: { currentAirport: "KLAX" } },
    );
    expect(w.find(".w").text()).toBe("KLAX");
  });
});
