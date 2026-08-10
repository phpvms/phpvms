import { mount } from "@vue/test-utils";
import { describe, expect, it, vi } from "vitest";
import AppShell from "@/app/shell/AppShell.vue";

describe("AppShell", () => {
  it("uses a mobile navigation drawer without forcing a desktop minimum width", async () => {
    vi.stubGlobal(
      "matchMedia",
      vi.fn().mockReturnValue({
        matches: true,
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
      }),
    );

    const wrapper = mount(AppShell, {
      slots: {
        navigation: '<a href="#dashboard">Dashboard</a>',
        header: "Header",
        main: "Main",
      },
    });
    await wrapper.vm.$nextTick();

    expect(wrapper.attributes("style")).toBeUndefined();

    const toggle = wrapper.get(".pv-mobile-nav-toggle");
    const navigation = wrapper.get(".pv-region-nav");

    expect(toggle.attributes("aria-expanded")).toBe("false");
    expect(navigation.attributes("aria-hidden")).toBe("true");

    await toggle.trigger("click");

    expect(toggle.attributes("aria-expanded")).toBe("true");
    expect(navigation.attributes("aria-hidden")).toBeUndefined();
    expect(navigation.classes()).toContain("is-open");

    await navigation.get("a").trigger("click");

    expect(toggle.attributes("aria-expanded")).toBe("false");

    await toggle.trigger("click");
    await wrapper.get(".pv-nav-backdrop").trigger("click");

    expect(toggle.attributes("aria-expanded")).toBe("false");
  });
});
