import { mount } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { describe, expect, it } from "vitest";
import DashboardToolbar from "@/widgets/dashboard/DashboardToolbar.vue";
import toolbarSource from "@/widgets/dashboard/DashboardToolbar.vue?raw";
import type { WidgetDef } from "@/widgets/dashboard/catalog";

const UButtonStub = defineComponent({
  name: "UButton",
  inheritAttrs: false,
  setup(_, { attrs, slots }) {
    return () => h("button", attrs, slots.default?.());
  },
});

const widget: WidgetDef = {
  id: "activity",
  component: "PvActivityFeed",
  title: "Activity",
  icon: "activity",
  defaultZone: "grid",
};

function mountToolbar(editing: boolean, availableWidgets: WidgetDef[]) {
  return mount(DashboardToolbar, {
    props: { editing, availableWidgets },
    global: {
      stubs: { UButton: UButtonStub },
      mocks: {
        $t: (key: string) =>
          ({
            "common.dashboard": "Dashboard",
            "ui.add_widget": "Add widget",
            "ui.all_widgets_placed": "All widgets placed",
            "ui.reset": "Reset",
            "ui.done": "Done",
            "ui.customize": "Customize",
          })[key] ?? key,
      },
    },
  });
}

describe("DashboardToolbar Nuxt UI contract", () => {
  it("preserves the Customize accessible name, event, hook, and 32px geometry", async () => {
    const wrapper = mountToolbar(false, []);

    expect(wrapper.classes()).toContain("pv-dashboard-toolbar");
    expect(wrapper.get("button").text()).toBe("Customize");
    await wrapper.get("button").trigger("click");
    expect(wrapper.emitted("toggleEditing")).toHaveLength(1);
    expect(toolbarSource).toMatch(/\.btn\s*\{[^}]*height:\s*32px/s);
  });

  it("preserves disabled Add widget behavior and Reset/Done events", async () => {
    const wrapper = mountToolbar(true, []);
    const buttons = wrapper.findAll("button");

    expect(buttons.map((button) => button.text())).toEqual(["Add widget", "Reset", "Done"]);
    expect(buttons[0]?.attributes("disabled")).toBeDefined();
    await buttons[1]?.trigger("click");
    await buttons[2]?.trigger("click");
    expect(wrapper.emitted("reset")).toHaveLength(1);
    expect(wrapper.emitted("toggleEditing")).toHaveLength(1);
  });

  it("opens the existing menu and emits the selected widget id", async () => {
    const wrapper = mountToolbar(true, [widget]);
    await wrapper.findAll("button")[0]?.trigger("click");

    expect(wrapper.get(".menu").text()).toContain("Activity");
    await wrapper.get(".menu-item").trigger("click");
    expect(wrapper.emitted("addWidget")).toEqual([["activity"]]);
    expect(wrapper.find(".menu").exists()).toBe(false);
  });
});
