import { flushPromises, mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h } from "vue";
import AirportWeather from "@/features/flights/AirportWeather.vue";
import BidAircraftPicker from "@/features/flights/BidAircraftPicker.vue";
import FlightBidDrawer from "@/features/flights/FlightBidDrawer.vue";
import SimBriefEditorDialog from "@/features/simbrief/SimBriefEditorDialog.vue";
import FlightManifest from "@/features/flights/FlightManifest.vue";
import type { DispatchPayload } from "@/features/flights/types";
import { useFlightBidDrawer } from "@/features/flights/useFlightBidDrawer";

const policy: App.Http.Data.FlightDispatchPolicyData = {
  aircraftRequired: true,
  chooseLaterAllowed: false,
  allowMultipleBids: false,
  pilotBidLimitReached: false,
  disableFlightOnBid: true,
  expireHours: 24,
  restrictToCompany: false,
  discoveryCurrentAirportOnly: false,
  requireCurrentAirport: false,
  restrictAircraftToRank: false,
  restrictAircraftToTypeRating: false,
  aircraftAtDepartureOnly: false,
  companyAircraftOnly: false,
  simbriefAvailable: false,
  simbriefRequiresBid: false,
  simbriefBlocksAircraft: true,
};

const summary: App.Http.Data.FlightListItemData = {
  id: "flight-1",
  callsign: "PVA104",
  dpt: "KDFW",
  arr: "KORD",
  distanceNm: 700,
  blockTime: "02:15",
  type: "J",
  airline: { icao: "PVA", name: "phpVMS Air" },
  bidId: null,
  scheduledDeparture: "14:00",
  scheduledArrival: "16:15",
  routeCode: "A",
  availability: "available",
  availabilityReason: null,
  primaryAction: "bid",
};

const flight: App.Http.Data.FlightDetailData = {
  summary,
  departure: {
    id: "KDFW",
    icao: "KDFW",
    name: "Dallas",
    lat: 32.9,
    lon: -97.0,
  },
  arrival: { id: "KORD", icao: "KORD", name: "Chicago", lat: 41.9, lon: -87.9 },
  alternate: null,
  departureWeather: { icao: "KDFW", timezone: "America/Chicago" },
  arrivalWeather: { icao: "KORD", timezone: "America/Chicago" },
  alternateWeather: null,
  scheduledDeparture: "14:00",
  scheduledArrival: "16:15",
  route: "MRF JALTU",
  cruiseLevel: 360,
  dispatchUrl: "/flights/flight-1/dispatch",
  simbriefPlanningUrl: "/simbrief/planning?flight_id=flight-1",
};

const aircraft: App.Http.Data.EligibleAircraftData = {
  id: 42,
  registration: "N104PV",
  icaoType: "A320",
  name: "Skylight",
  subfleetId: 4,
  subfleetName: "A320-200",
  airport: { icao: "KDFW", name: "Dallas" },
  state: "Parked",
  status: "Active",
};

const subfleet: App.Http.Data.EligibleSubfleetData = {
  id: 4,
  airlineIcao: "PVA",
  airlineName: "phpVMS Air",
  icaoType: "A320",
  displayName: "PVA A320-200",
  eligibleAircraftCount: 1,
  disabled: false,
  availabilityLabel: null,
};

function dispatchPayload(selection: App.Http.Data.BidSelectionData | null = null): DispatchPayload {
  return { flight, policy, subfleets: [subfleet], selection };
}

function selection(): App.Http.Data.BidSelectionData {
  return {
    bid: { id: 9, flightId: summary.id, aircraftId: aircraft.id },
    flight: {
      ...flight,
      summary: { ...summary, bidId: 9, primaryAction: "overview" },
    },
    aircraft,
    policy,
    state: "confirmed",
    expiresAt: "2026-08-11T14:00:00Z",
    aircraftReserved: true,
  };
}

const buttonStub = defineComponent({
  inheritAttrs: false,
  props: { disabled: Boolean, loading: Boolean, type: String },
  emits: ["click"],
  setup:
    (props, { attrs, emit, slots }) =>
    () =>
      h(
        "button",
        {
          ...attrs,
          type: props.type ?? "button",
          disabled: props.disabled,
          onClick: (event: MouseEvent) => emit("click", event),
        },
        slots.default?.(),
      ),
});
const drawerStub = defineComponent({
  props: { open: Boolean },
  emits: ["update:open"],
  template: '<div class="drawer-stub" :data-open="open"><slot name="content" /></div>',
});

beforeEach(() => {
  document.head.innerHTML = '<meta name="csrf-token" content="csrf-123">';
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe("flight bid state", () => {
  it("loads eligible aircraft and reopens an existing bid in overview state", async () => {
    const responses = [dispatchPayload(), dispatchPayload(selection())];
    vi.stubGlobal(
      "fetch",
      vi.fn(() =>
        Promise.resolve(new Response(JSON.stringify(responses.shift()), { status: 200 })),
      ),
    );
    const state = useFlightBidDrawer();

    state.show(summary.id);
    expect(state.state.value).toBe("loading");
    await flushPromises();
    expect(state.state.value).toBe("selecting");
    expect(state.payload.value?.subfleets).toHaveLength(1);

    state.show(summary.id);
    await flushPromises();
    expect(state.state.value).toBe("overview");
    expect(state.payload.value?.selection?.bid.id).toBe(9);
  });

  it("does not advance until the server confirms the bid", async () => {
    let confirmResponse: ((response: Response) => void) | undefined;
    vi.stubGlobal(
      "fetch",
      vi
        .fn()
        .mockResolvedValueOnce(new Response(JSON.stringify(dispatchPayload()), { status: 200 }))
        .mockImplementationOnce(
          () =>
            new Promise<Response>((resolve) => {
              confirmResponse = resolve;
            }),
        ),
    );
    const state = useFlightBidDrawer();
    state.show(summary.id);
    await flushPromises();
    state.selectedAircraftId.value = aircraft.id;
    const request = state.submit();

    expect(state.state.value).toBe("submitting");
    expect(state.payload.value?.selection).toBeNull();
    confirmResponse?.(new Response(JSON.stringify({ selection: selection() }), { status: 200 }));
    await request;
    expect(state.state.value).toBe("overview");
  });

  it("refreshes choices after an aircraft conflict while preserving flight context", async () => {
    vi.stubGlobal(
      "fetch",
      vi
        .fn()
        .mockResolvedValueOnce(new Response(JSON.stringify(dispatchPayload()), { status: 200 }))
        .mockResolvedValueOnce(
          new Response(
            JSON.stringify({
              type: "aircraft-conflict",
              message: "Aircraft is no longer available.",
            }),
            { status: 409 },
          ),
        )
        .mockResolvedValueOnce(
          new Response(JSON.stringify(dispatchPayload()), {
            status: 200,
          }),
        ),
    );
    const state = useFlightBidDrawer();
    state.show(summary.id);
    await flushPromises();
    state.selectedAircraftId.value = aircraft.id;

    await state.submit();
    expect(state.failure.value?.type).toBe("aircraft-conflict");
    expect(state.payload.value?.flight.summary.id).toBe(summary.id);
    expect(state.payload.value?.subfleets).toHaveLength(1);
    expect(state.selectedAircraftId.value).toBeNull();
  });

  it("preserves aircraft selection after a network error", async () => {
    vi.stubGlobal(
      "fetch",
      vi
        .fn()
        .mockResolvedValueOnce(new Response(JSON.stringify(dispatchPayload()), { status: 200 }))
        .mockRejectedValueOnce(new Error("offline")),
    );
    const state = useFlightBidDrawer();
    state.show(summary.id);
    await flushPromises();
    state.selectedAircraftId.value = aircraft.id;

    await state.submit();
    expect(state.failure.value?.type).toBe("network");
    expect(state.selectedAircraftId.value).toBe(aircraft.id);
  });

  it("blocks a required-aircraft submission before sending a request", async () => {
    const fetch = vi
      .fn()
      .mockResolvedValueOnce(new Response(JSON.stringify(dispatchPayload()), { status: 200 }));
    vi.stubGlobal("fetch", fetch);
    const state = useFlightBidDrawer();
    state.show(summary.id);
    await flushPromises();

    await state.submit();
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(state.failure.value?.type).toBe("validation");
    expect(state.state.value).toBe("selecting");
  });
});

describe("flight bid UI", () => {
  it("offers searchable rich-card menus and no flight-only bypass when aircraft is required", () => {
    const required = mount(BidAircraftPicker, {
      props: {
        subfleets: [subfleet],
        aircraft: [aircraft],
        subfleetId: null,
        aircraftId: null,
        required: true,
        loadingAircraft: false,
      },
      global: { stubs: { USelectMenu: true } },
    });
    expect(required.text()).toContain("Eligible subfleet");
    expect(required.text()).not.toContain("Choose later");

    const optional = mount(BidAircraftPicker, {
      props: {
        subfleets: [subfleet],
        aircraft: [aircraft],
        subfleetId: null,
        aircraftId: null,
        required: false,
        loadingAircraft: false,
      },
      global: { stubs: { USelectMenu: true } },
    });
    expect(optional.text()).toContain("Choose later");
  });

  it("announces loading, disables duplicate submission, and keeps the drawer open", async () => {
    let loadResponse: ((response: Response) => void) | undefined;
    vi.stubGlobal(
      "fetch",
      vi.fn(
        () =>
          new Promise<Response>((resolve) => {
            loadResponse = resolve;
          }),
      ),
    );
    const wrapper = mount(FlightBidDrawer, {
      global: {
        stubs: {
          UButton: buttonStub,
          UDrawer: drawerStub,
          USelectMenu: true,
          PvSlot: true,
          NavDisplay: true,
        },
      },
    });

    wrapper.vm.show(summary.id);
    await wrapper.vm.$nextTick();
    expect(wrapper.get("[role=status]").text()).toContain("Loading policy and aircraft");
    expect(wrapper.get(".drawer-stub").attributes("data-open")).toBe("true");

    loadResponse?.(new Response(JSON.stringify(dispatchPayload()), { status: 200 }));
    await flushPromises();
    expect(wrapper.text()).toContain("Choose a subfleet first");
    const select = wrapper.findAll("button").find((button) => button.text() === "Select");
    expect(select?.attributes()).toHaveProperty("disabled");
  });

  it("renders schedule hierarchy, availability, real detail routes, and generic addon slots", () => {
    const wrapper = mount(FlightManifest, {
      props: { flights: [summary], policy },
      global: {
        stubs: {
          UButton: buttonStub,
          PvSlot: {
            props: ["name"],
            template: '<i class="slot" :data-name="name" />',
          },
          Link: { props: ["href"], template: '<a :href="href"><slot /></a>' },
        },
      },
    });
    expect(wrapper.text()).toContain("PVA104");
    expect(wrapper.text()).toContain("Available");
    expect(wrapper.get('a[href="/flights/flight-1"]').attributes("href")).toBe("/flights/flight-1");
    expect(wrapper.findAll(".slot").map((slot) => slot.attributes("data-name"))).toEqual([
      "flights.row.actions",
      "flights.detail.actions",
    ]);
  });
});

describe("SimBrief embedded editor host", () => {
  it("announces provider loading and keeps cross-origin control explicit", async () => {
    const wrapper = mount(SimBriefEditorDialog, {
      props: {
        open: true,
        editorUrl: "https://dispatch.simbrief.com/edit",
        flightLabel: "PVA104 · KDFW → KORD",
      },
      global: { stubs: { UModal: drawerStub, UButton: buttonStub } },
    });
    await wrapper.vm.$nextTick();
    expect(wrapper.get("[role=status]").text()).toContain("Opening SimBrief editor");
    expect(wrapper.text()).toContain("cannot inspect or control its cross-origin content");
    expect(wrapper.get("iframe").attributes("title")).toBe("SimBrief OFP editor");
  });
});

describe("airport weather", () => {
  it("renders current, missing, stale, provider-error, and no-alternate states", async () => {
    const cases = [
      {
        response: {
          icao: "KDFW",
          metar: "KDFW CURRENT",
          observedAt: "now",
          isStale: false,
        },
        text: "KDFW CURRENT",
      },
      {
        response: {
          icao: "KDFW",
          metar: null,
          observedAt: null,
          isStale: false,
        },
        text: "No current report",
      },
      {
        response: {
          icao: "KDFW",
          metar: "KDFW OLD",
          observedAt: "earlier",
          isStale: true,
        },
        text: "Stale report",
      },
    ];

    for (const item of cases) {
      vi.stubGlobal(
        "fetch",
        vi.fn(() => Promise.resolve(new Response(JSON.stringify(item.response), { status: 200 }))),
      );
      const wrapper = mount(AirportWeather, {
        props: {
          label: "Departure",
          station: { icao: "KDFW", timezone: null },
        },
        global: { stubs: { UButton: buttonStub } },
      });
      await wrapper.vm.$nextTick();
      expect(wrapper.text()).toContain("Loading current report");
      await flushPromises();
      expect(wrapper.text()).toContain(item.text);
      wrapper.unmount();
    }

    vi.stubGlobal(
      "fetch",
      vi.fn(() => Promise.resolve(new Response("", { status: 500 }))),
    );
    const error = mount(AirportWeather, {
      props: { label: "Arrival", station: { icao: "KORD", timezone: null } },
      global: { stubs: { UButton: buttonStub } },
    });
    await flushPromises();
    expect(error.get("[role=alert]").text()).toContain("Weather provider error");

    const alternate = mount(AirportWeather, {
      props: {
        label: "Alternate",
        station: null,
        emptyLabel: "No alternate airport",
      },
      global: { stubs: { UButton: buttonStub } },
    });
    await flushPromises();
    expect(alternate.text()).toContain("No alternate airport");
  });
});
