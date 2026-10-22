import { mount } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { describe, expect, it, vi } from "vitest";
import PirepCard from "@/components/pireps/PirepCard.vue";
import { stateBadgeColor } from "@/components/pireps/stateBadgeColor";

vi.mock("@inertiajs/vue3", async () => {
  const { defineComponent, h } = await import("vue");

  return {
    Link: defineComponent({
      name: "InertiaLink",
      props: { href: { type: String, required: true } },
      setup:
        (props, { slots }) =>
        () =>
          h("a", { href: props.href, "data-inertia": "true" }, slots.default?.()),
    }),
  };
});

/**
 * The shared Nuxt UI stub only renders a component's default slot, so a card
 * built out of #header/#body/#footer would come back empty. This stub keeps
 * UPageCard's real contract: render `as` at the root with the attrs (the
 * Inertia href) on it, then the named slots in order.
 */
const UPageCard = defineComponent({
  name: "UPageCard",
  inheritAttrs: false,
  props: { as: { type: [String, Object], default: "div" } },
  setup:
    (props, { attrs, slots }) =>
    () =>
      h(props.as, attrs, [slots.header?.(), slots.body?.(), slots.footer?.(), slots.default?.()]),
});

function pirep(overrides: Partial<App.Http.Data.PirepListItemData> = {}) {
  return {
    id: "abc123",
    ident: "PVA100",
    dpt: "KJFK",
    dptName: "John F Kennedy Intl",
    arr: "KLAX",
    arrName: "Los Angeles Intl",
    aircraft: "N123 · A320",
    flightTime: "05:30",
    distance: "2145 nmi",
    score: 96,
    landingRate: -180,
    state: "Accepted",
    stateColor: "success",
    submittedAt: "2026-02-11T14:05:00+00:00",
    ...overrides,
  } as App.Http.Data.PirepListItemData;
}

describe("PirepCard", () => {
  it("renders the whole card as an Inertia link to the PIREP", () => {
    // UPageCard passes `as` and `$attrs` straight to its root Primitive, so the
    // card itself is the Inertia Link and role="listitem" lands on it too.
    const wrapper = mount(PirepCard, {
      props: { pirep: pirep() },
      global: { stubs: { UPageCard } },
    });
    const link = wrapper.find("[data-inertia]");

    expect(link.exists()).toBe(true);
    expect(link.attributes("href")).toBe("/pireps/abc123");
  });

  it("shows the ident, both airports and the stat strip", () => {
    const wrapper = mount(PirepCard, {
      props: { pirep: pirep() },
      global: { stubs: { UPageCard } },
    });
    const text = wrapper.text();

    expect(text).toContain("PVA100");
    expect(text).toContain("KJFK");
    expect(text).toContain("Los Angeles Intl");
    expect(text).toContain("05:30");
    expect(text).toContain("96");
  });

  it("falls back to an em dash for the facts a PIREP has not got yet", () => {
    const wrapper = mount(PirepCard, {
      global: { stubs: { UPageCard } },
      props: {
        pirep: pirep({
          aircraft: null,
          flightTime: null,
          distance: null,
          score: null,
          submittedAt: null,
        }),
      },
    });

    expect(wrapper.text().match(/—/g)).toHaveLength(5);
  });
});

describe("stateBadgeColor", () => {
  it.each([
    ["success", "success"],
    ["warning", "warning"],
    ["info", "info"],
    // The two tokens whose names differ between the DTO and Nuxt UI.
    ["danger", "error"],
    ["gray", "neutral"],
  ])("maps %s to %s", (token, expected) => {
    expect(stateBadgeColor(token)).toBe(expected);
  });

  it("falls back to neutral for a token it does not know", () => {
    expect(stateBadgeColor("chartreuse")).toBe("neutral");
  });
});
