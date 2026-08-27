import { mount } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import Bids from "@/pages/Flights/Bids.vue";

const buttonStub = defineComponent({
  props: { type: String, disabled: Boolean, size: String, variant: String, to: String },
  emits: ["click"],
  setup(props, { emit, slots }) {
    return () =>
      props.to
        ? h("a", { href: props.to }, slots.default?.())
        : h(
            "button",
            {
              type: props.type ?? "button",
              disabled: props.disabled,
              onClick: (event: MouseEvent) => emit("click", event),
            },
            slots.default?.(),
          );
  },
});

/**
 * The card is built from Nuxt UI layout components whose content lives in
 * NAMED slots (`#header`, `#body`, `#name`, ...) and in text props (UEmpty's
 * `title`/`description`). An unresolved component drops both, leaving the
 * card empty — this stub renders every slot it is handed, plus those props.
 */
const slotsStub = defineComponent({
  props: { title: String, description: String, label: String, to: String },
  setup(props, { slots }) {
    return () =>
      h(props.to ? "a" : "div", props.to ? { href: props.to } : {}, [
        props.title,
        props.description,
        props.label,
        ...Object.values(slots).map((slot) => slot?.()),
      ]);
  },
});

const passthroughStub = slotsStub;

const linkStub = defineComponent({
  props: { href: String },
  template: '<a :href="href"><slot /></a>',
});

const stubs = {
  UButton: buttonStub,
  UPageCard: slotsStub,
  UBlogPost: slotsStub,
  UPage: passthroughStub,
  UPageBody: passthroughStub,
  UPageGrid: passthroughStub,
  UPageHeader: slotsStub,
  UBadge: passthroughStub,
  UEmpty: slotsStub,
  UUser: slotsStub,
  PvSlot: true,
  Link: linkStub,
};

const row: App.Http.Data.BidRowData = {
  bid: { id: 9, flightId: "flight-1", aircraftId: 42, userTourId: null },
  flight: {
    id: "flight-1",
    callsign: "PVA104",
    dpt: "KDFW",
    arr: "KORD",
    distanceNm: 700,
    blockTime: "02:15",
    type: "J",
    airline: null,
    bidId: 9,
    scheduledDeparture: "14:00",
    scheduledArrival: "16:15",
    routeCode: "A",
    availability: "bid",
    availabilityReason: null,
    primaryAction: "overview",
  },
  aircraft: {
    id: 42,
    registration: "N104PV",
    icaoType: "A320",
    name: "Skylight",
    subfleetId: 4,
    subfleetName: "A320-200",
    airport: { icao: "KDFW", name: "Dallas" },
    state: "Parked",
    status: "Active",
  } as App.Http.Data.BidRowData["aircraft"],
  state: "confirmed",
  expiresAt: "2026-08-11T14:00:00Z",
  canGenerateSimBrief: true,
  canRemove: true,
  tourName: null,
  tourId: null,
  tourLeg: null,
  ofpUrl: null,
};

/** The same bid as one leg of a tour run. */
const tourLeg: App.Http.Data.BidRowData = {
  ...row,
  bid: { ...row.bid, id: 11, flightId: "flight-2", userTourId: "run-1" },
  tourName: "Pacific Crossing",
  tourId: 7,
  tourLeg: 2,
};

function mountBids(bids: App.Http.Data.BidRowData[]) {
  return mount(Bids, { props: { bids }, global: { stubs } });
}

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("My Bids", () => {
  it("shows the flight identity, its numbers and a link to the flight", () => {
    const wrapper = mountBids([row]);

    expect(wrapper.text()).toContain("PVA104");
    expect(wrapper.text()).toContain("KDFW");
    expect(wrapper.text()).toContain("KORD");
    expect(wrapper.text()).toContain("N104PV");
    expect(wrapper.text()).toContain("02:15");
    expect(wrapper.text()).toContain("700NM");
    expect(wrapper.html()).toContain('href="/flights/flight-1"');
  });

  it("offers SimBrief planning, and a briefing link once one exists", () => {
    expect(mountBids([row]).html()).toContain("/ofp/planning?bid_id=9");

    const briefed = mountBids([{ ...row, ofpUrl: "/ofp/briefings/44" }]);
    expect(briefed.text()).toContain("View OFP");
    expect(briefed.html()).toContain("/ofp/briefings/44");
    expect(briefed.html()).not.toContain("/ofp/planning?bid_id=9");
  });

  it("renders a dash when no aircraft is selected", () => {
    const wrapper = mountBids([{ ...row, aircraft: null }]);

    expect(wrapper.text()).toContain("—");
    expect(wrapper.text()).not.toContain("N104PV");
  });

  it("confirms removal and drops the card without a page reload", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(() => Promise.resolve(new Response("{}", { status: 200 }))),
    );
    const wrapper = mountBids([row]);

    const cancel = wrapper.findAll("button").find((button) => button.text() === "Cancel");
    await cancel?.trigger("click");
    expect(wrapper.text()).toContain("Cancel this bid?");

    const confirm = wrapper.findAll("button").find((button) => button.text() === "Cancel bid");
    await confirm?.trigger("click");
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(fetch).toHaveBeenCalledWith(
      "/flights/flight-1/bid",
      expect.objectContaining({ method: "DELETE" }),
    );
    expect(wrapper.text()).toContain("No bids yet");
  });

  it("groups tour legs under the tour, with one cancel for the whole run", async () => {
    const wrapper = mountBids([tourLeg, row]);

    expect(wrapper.text()).toContain("Pacific Crossing");
    expect(wrapper.text()).toContain("1 leg");
    expect(wrapper.html()).toContain('href="/tours/7"');

    // The leg card itself offers no cancel — that action belongs to the run.
    const cancels = wrapper.findAll("button").filter((button) => button.text() === "Cancel tour");
    expect(cancels).toHaveLength(1);

    await cancels[0].trigger("click");
    expect(wrapper.text()).toContain("Cancel the whole tour?");
  });

  it("drops every leg of a tour when the run is cancelled", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(() => Promise.resolve(new Response("{}", { status: 200 }))),
    );
    const secondLeg = { ...tourLeg, bid: { ...tourLeg.bid, id: 12, flightId: "flight-3" } };
    const wrapper = mountBids([tourLeg, secondLeg]);

    await wrapper
      .findAll("button")
      .find((button) => button.text() === "Cancel tour")
      ?.trigger("click");
    await wrapper
      .findAll("button")
      .find((button) => button.text() === "Yes, cancel it")
      ?.trigger("click");
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(fetch).toHaveBeenCalledWith(
      "/flights/flight-2/bid",
      expect.objectContaining({ method: "DELETE" }),
    );
    expect(wrapper.text()).toContain("No bids yet");
  });
});
