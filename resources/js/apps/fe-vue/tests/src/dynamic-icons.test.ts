import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import PvIcon from "@/shared/components/PvIcon.vue";
import { dynamicIcons, fallbackIcon, resolveIcon } from "@/shared/lib/dynamicIcons";
import { dynamicTablerIcons } from "../../icon.config";

describe("dynamic icon map", () => {
  it("covers exactly the names the backend is allowed to send", () => {
    expect(Object.keys(dynamicIcons).sort()).toEqual([...dynamicTablerIcons].sort());
  });

  it("resolves a name in any of the forms a widget def may use", () => {
    const plane = dynamicIcons["i-tabler-plane-arrival"];
    expect(resolveIcon("i-tabler-plane-arrival")).toBe(plane);
    expect(resolveIcon("plane-arrival")).toBe(plane);
    expect(resolveIcon("PlaneArrival")).toBe(plane);
    expect(resolveIcon(" planeArrival ")).toBe(plane);
  });

  it("falls back for a name phpVMS does not bundle", () => {
    expect(resolveIcon("i-tabler-not-a-bundled-icon")).toBe(fallbackIcon);
  });
});

describe("PvIcon", () => {
  it("renders the mapped icon at the requested size", () => {
    const wrapper = mount(PvIcon, { props: { name: "wallet", size: 15 } });
    const svg = wrapper.get("svg");

    expect(svg.attributes("width")).toBe("15");
    expect(svg.attributes("height")).toBe("15");
    expect(svg.html()).toContain("currentColor");
  });

  it("renders raw SVG child markup as-is", () => {
    const wrapper = mount(PvIcon, { props: { name: '<circle cx="12" cy="12" r="9" />' } });

    expect(wrapper.get("svg").html()).toContain("<circle");
  });
});
