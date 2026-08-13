import { mount } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";
import Bids from "@/pages/Flights/Bids.vue";

const show = vi.fn();

const buttonStub = defineComponent({
  props: { type: String, disabled: Boolean, size: String, variant: String },
  emits: ["click"],
  setup(props, { emit, slots }) {
    return () =>
      h(
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

const drawerStub = defineComponent({
  emits: ["closed"],
  setup(_, { expose }) {
    expose({ show });
    return () => h("aside");
  },
});

const row = {
  bid: { id: 9, flightId: "flight-1", aircraftId: 42 },
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
  },
  state: "confirmed",
  expiresAt: "2026-08-11T14:00:00Z",
  canGenerateSimBrief: true,
  canRemove: true,
};

afterEach(() => {
  vi.unstubAllGlobals();
  show.mockReset();
});

describe("My Bids", () => {
  it("keeps the operational route hierarchy and opens the shared overview", async () => {
    const wrapper = mount(Bids, {
      props: { bids: [row], acarsPlugin: false },
      global: {
        stubs: {
          UButton: buttonStub,
          AssignmentDrawer: drawerStub,
          PvSlot: true,
          Link: defineComponent({
            props: { href: String },
            template: '<a :href="href"><slot /></a>',
          }),
        },
      },
    });

    expect(wrapper.text()).toContain("PVA104");
    expect(wrapper.text()).toContain("KDFW");
    expect(wrapper.text()).toContain("KORD");
    expect(wrapper.text()).toContain("N104PV");
    expect(wrapper.text()).toContain("confirmed");
    expect(wrapper.text()).toContain("Generate OFP");
    expect(wrapper.html()).toContain('href="/flights/flight-1"');

    await wrapper.get("button").trigger("click");
    expect(show).toHaveBeenCalledWith("flight-1");
  });

  it("keeps SimBrief planning available for a flight-only bid", () => {
    const wrapper = mount(Bids, {
      props: { bids: [{ ...row, aircraft: null }], acarsPlugin: false },
      global: {
        stubs: {
          UButton: buttonStub,
          AssignmentDrawer: drawerStub,
          PvSlot: true,
          Link: defineComponent({
            props: { href: String },
            template: '<a :href="href"><slot /></a>',
          }),
        },
      },
    });

    expect(wrapper.text()).toContain("Aircraft not selected");
    expect(wrapper.text()).toContain("Generate OFP");
    expect(wrapper.html()).toContain("/ofp/planning?bid_id=9");
  });

  it("confirms removal and removes the row without a page reload", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(() =>
        Promise.resolve(
          new Response(
            JSON.stringify({ flightUrl: "/flights/flight-1", bidsUrl: "/flights/bids" }),
            { status: 200 },
          ),
        ),
      ),
    );
    const wrapper = mount(Bids, {
      props: { bids: [row], acarsPlugin: false },
      global: {
        stubs: {
          UButton: buttonStub,
          AssignmentDrawer: drawerStub,
          PvSlot: true,
          Link: defineComponent({
            props: { href: String },
            template: '<a :href="href"><slot /></a>',
          }),
        },
      },
    });

    const remove = wrapper.findAll("button").find((button) => button.text() === "Remove");
    await remove?.trigger("click");
    expect(wrapper.text()).toContain("Remove this bid?");
    const confirm = wrapper.findAll("button").find((button) => button.text() === "Confirm removal");
    await confirm?.trigger("click");
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(fetch).toHaveBeenCalledWith(
      "/flights/flight-1/bid",
      expect.objectContaining({ method: "DELETE" }),
    );
    expect(wrapper.text()).toContain("No bids yet");
  });
});
