import { shallowMount } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { describe, expect, it, vi } from "vitest";
import PvApp from "@/app/PvApp.vue";
import { ThemeContextKey, defaultThemeDocument, resolveTheme } from "@/shared/theme";

vi.mock("@inertiajs/vue3", () => ({
  usePage: () => ({ props: {} }),
}));

const UAppStub = defineComponent({
  name: "UApp",
  setup(_, { slots }) {
    return () => h("div", { "data-u-app": "" }, slots.default?.());
  },
});

const UThemeStub = defineComponent({
  name: "UTheme",
  props: {
    props: { type: Object, required: true },
    ui: { type: Object, required: true },
  },
  setup(_, { slots }) {
    return () => h("div", { "data-u-theme": "" }, slots.default?.());
  },
});

describe("PvApp theme providers", () => {
  it("hosts UApp and the resolved UTheme on the first render", () => {
    const document = defaultThemeDocument();
    document.nuxtUi.components!.button!.style!.shape = "pill";
    const resolved = resolveTheme(document);

    const wrapper = shallowMount(PvApp, {
      global: {
        provide: {
          [ThemeContextKey as symbol]: { document, resolved },
        },
        stubs: {
          UApp: UAppStub,
          UTheme: UThemeStub,
        },
      },
    });

    expect(wrapper.findComponent(UAppStub).exists()).toBe(true);
    expect(wrapper.getComponent(UThemeStub).props("props")).toEqual(resolved.props);
    expect(wrapper.getComponent(UThemeStub).props("ui")).toEqual(resolved.ui);
  });
});
