import { describe, it, expect, beforeEach } from "vitest";
import {
  getCatalog,
  tagWidgets,
  mergeServerWidgets,
  type WidgetDef,
} from "@/widgets/dashboard/catalog";

const base = { title: "X", icon: "", defaultZone: "grid" as const };

beforeEach(() => mergeServerWidgets([])); // reset module state between tests

// `kind: 'tag'` entries (web-component-widget-floor) share the same server
// list as dashboard widgets but are a DIFFERENT catalog — a custom element for
// a page editor's insert menu, not something placeable on the pilot's board.
describe("tag-kind widgets are a separate catalog", () => {
  it("a tag-kind entry is readable via tagWidgets(), not getCatalog()", () => {
    const tagDef: WidgetDef = {
      ...base,
      id: "hero",
      kind: "tag",
      tag: "pv-hero",
      module: "/build/skylight/elements.js",
      attributes: [{ name: "heading", type: "string", description: "The hero heading." }],
    };
    mergeServerWidgets([tagDef]);

    expect(tagWidgets()).toEqual([tagDef]);
    expect(getCatalog().some((w) => w.id === "hero")).toBe(false);
  });

  it("a mixed batch splits vue/blade widgets from tag widgets", () => {
    const vueDef: WidgetDef = { ...base, id: "v", component: "HoursWidget" };
    const tagDef: WidgetDef = { ...base, id: "t", kind: "tag", tag: "pv-callout" };
    mergeServerWidgets([vueDef, tagDef]);

    expect(getCatalog().some((w) => w.id === "v")).toBe(true);
    expect(getCatalog().some((w) => w.id === "t")).toBe(false);
    expect(tagWidgets()).toEqual([tagDef]);
    expect(tagWidgets().some((w) => w.id === "v")).toBe(false);
  });

  it("resets to empty when merging an empty/undefined batch", () => {
    mergeServerWidgets([{ ...base, id: "t", kind: "tag", tag: "pv-hero" }]);
    expect(tagWidgets()).toHaveLength(1);

    mergeServerWidgets([]);
    expect(tagWidgets()).toHaveLength(0);
  });
});
