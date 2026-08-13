import { mount } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { describe, expect, it, vi } from "vitest";
import FlightManifest from "@/components/flights/FlightManifest.vue";

vi.mock("@inertiajs/vue3", async () => {
  const { defineComponent, h } = await import("vue");

  return {
    Link: defineComponent({
      props: { href: { type: String, required: true } },
      setup:
        (props, { slots }) =>
        () =>
          h(
            "a",
            { href: props.href, onClick: (event: MouseEvent) => event.preventDefault() },
            slots.default?.(),
          ),
    }),
  };
});

const policy: App.Http.Data.FlightDispatchPolicyData = {
  aircraftRequired: false,
  chooseLaterAllowed: true,
  allowMultipleBids: true,
  pilotBidLimitReached: false,
  disableFlightOnBid: false,
  expireHours: 0,
  restrictToCompany: false,
  discoveryCurrentAirportOnly: false,
  requireCurrentAirport: false,
  restrictAircraftToRank: false,
  restrictAircraftToTypeRating: false,
  aircraftAtDepartureOnly: false,
  companyAircraftOnly: false,
  simbriefEnabled: false,
  simbriefRequiresBid: false,
  simbriefBlocksAircraft: false,
};

function makeFlight(id: string, callsign: string): App.Http.Data.FlightListItemData {
  return {
    id,
    callsign,
    dpt: "KDFW",
    arr: "KORD",
    distanceNm: 700,
    blockTime: "02:15",
    type: "J",
    airline: { icao: "PVA", name: "phpVMS Air", logo: null },
    bidId: null,
    scheduledDeparture: "14:00",
    scheduledArrival: "16:15",
    routeCode: "A",
    availability: "available",
    availabilityReason: null,
    primaryAction: "bid",
  };
}

const buttonStub = defineComponent({
  inheritAttrs: false,
  props: { block: Boolean, disabled: Boolean, loading: Boolean, type: String },
  emits: ["click"],
  setup:
    (props, { attrs, emit, slots }) =>
    () =>
      h(
        "button",
        {
          ...attrs,
          type: props.type,
          disabled: props.disabled,
          onClick: (event: MouseEvent) => emit("click", event),
        },
        slots.default?.(),
      ),
});

const flightInfoStub = defineComponent({
  props: { arrival: String, callsign: String, departure: String },
  template: '<span class="pv-flight-info">{{ callsign }} {{ departure }} {{ arrival }}</span>',
});

const slotStub = defineComponent({
  props: { name: { type: String, required: true } },
  setup: (props) => () =>
    props.name === "flights.row.actions"
      ? h("button", { class: "addon-action", type: "button" }, "Addon")
      : h("span", { class: "detail-addon" }, "Detail addon"),
});

function mountManifest(onBid = vi.fn()) {
  return mount(FlightManifest, {
    props: {
      flights: [makeFlight("flight-1", "PVA104"), makeFlight("flight-2", "PVA105")],
      policy,
      onBid,
    },
    global: {
      stubs: {
        UButton: buttonStub,
        PvFlightInfo: flightInfoStub,
        PvSlot: slotStub,
      },
    },
  });
}

describe("flight manifest row activation", () => {
  it("selects a row with click, Enter, or Space and exposes visible focus semantics", async () => {
    const wrapper = mountManifest();
    const rows = wrapper.findAll(".manifest-row");

    expect(rows[1].attributes("tabindex")).toBe("0");
    expect(rows[1].attributes("aria-label")).toBe("Show PVA105 operational context");
    expect(rows[1].attributes("aria-current")).toBeUndefined();

    await rows[1].trigger("click");
    expect(wrapper.get(".selected-flight").text()).toContain("PVA105");
    expect(rows[1].attributes("aria-current")).toBe("true");

    await rows[0].trigger("keydown", { key: "Enter" });
    expect(wrapper.get(".selected-flight").text()).toContain("PVA104");

    await rows[1].trigger("keydown", { key: " " });
    expect(wrapper.get(".selected-flight").text()).toContain("PVA105");
  });

  it("keeps details, bid, and addon actions independent from row selection", async () => {
    const onBid = vi.fn();
    const wrapper = mountManifest(onBid);
    const secondRow = wrapper.findAll(".manifest-row")[1];

    await secondRow.get(".detail-link").trigger("click");
    expect(wrapper.get(".selected-flight").text()).toContain("PVA104");

    const bidButton = secondRow.findAll("button").find((button) => button.text() === "Bid");
    expect(bidButton).toBeDefined();
    await bidButton?.trigger("click");
    expect(onBid).toHaveBeenCalledWith("flight-2", expect.any(MouseEvent));
    expect(wrapper.get(".selected-flight").text()).toContain("PVA104");

    await secondRow.get(".addon-action").trigger("click");
    expect(wrapper.get(".selected-flight").text()).toContain("PVA104");
  });
});
