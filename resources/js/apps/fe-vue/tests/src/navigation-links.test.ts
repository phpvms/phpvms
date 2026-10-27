import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import NavigationLinks from "@/app/shell/NavigationLinks.vue";
import { activeMenuPath, type NavigationMenuItem } from "@/app/shell/navigation";

const origin = window.location.origin;

function item(
  label: string,
  path: string,
  overrides: Partial<NavigationMenuItem> = {},
): NavigationMenuItem {
  return {
    label,
    url: path === "" ? "" : `${origin}${path}`,
    icon: null,
    new_window: false,
    spa: true,
    children: [],
    ...overrides,
  };
}

function mountLinks(items: NavigationMenuItem[], activePath: string | null = null) {
  return mount(NavigationLinks, {
    props: { items, activePath },
    global: {
      mocks: { $t: (key: string) => key },
      stubs: { Link: { template: '<a class="inertia-link"><slot /></a>' } },
    },
  });
}

describe("NavigationLinks", () => {
  it("renders the menu from the shared prop, nesting children under their parent", () => {
    const wrapper = mountLinks([
      item("Flights", "/flights", { children: [item("My Bids", "/flights/bids")] }),
      item("Docs", "/docs"),
    ]);

    const labels = wrapper.findAll(".item .lbl").map((label) => label.text());
    const bids = wrapper.findAll(".item")[1];

    expect(labels).toEqual(["Flights", "My Bids", "Docs"]);
    expect(bids.attributes("style")).toContain("padding-inline-start: 26px");
  });

  it("uses an in-app visit for spa items and a plain link otherwise", () => {
    const wrapper = mountLinks([
      item("Flights", "/flights"),
      item("Docs", "/docs", { spa: false, url: "https://docs.phpvms.net" }),
      item("Discord", "/discord", { new_window: true }),
    ]);

    const [flights, docs, discord] = wrapper.findAll(".item");

    expect(flights.classes()).toContain("inertia-link");
    expect(docs.classes()).not.toContain("inertia-link");
    expect(docs.attributes("href")).toBe("https://docs.phpvms.net");
    expect(discord.attributes("target")).toBe("_blank");
    expect(discord.attributes("rel")).toBe("noopener noreferrer");
  });

  it("renders an item with no link as a group label", () => {
    const wrapper = mountLinks([item("Resources", "", { children: [item("Docs", "/docs")] })]);

    expect(wrapper.find("span.item.group").text()).toBe("Resources");
  });

  it("marks the item for the current page", () => {
    const wrapper = mountLinks([item("Flights", "/flights"), item("Tours", "/tours")], "/tours");

    expect(wrapper.findAll(".item")[1].attributes("aria-current")).toBe("page");
    expect(wrapper.findAll(".item")[0].attributes("aria-current")).toBeUndefined();
  });
});

describe("activeMenuPath", () => {
  it("picks the longest menu path the page sits under", () => {
    const items = [item("Flights", "/flights", { children: [item("My Bids", "/flights/bids")] })];

    expect(activeMenuPath(items, "/flights/bids")).toBe("/flights/bids");
    expect(activeMenuPath(items, "/flights/42")).toBe("/flights");
    expect(activeMenuPath(items, "/tours")).toBeNull();
  });

  it("ignores external URLs", () => {
    expect(
      activeMenuPath([item("Docs", "", { url: "https://docs.phpvms.net/flights" })], "/flights"),
    ).toBeNull();
  });
});
