import { flushPromises, mount } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h } from "vue";
import { router } from "@inertiajs/vue3";
import AircraftCard from "@/components/assignments/AircraftCard.vue";
import AircraftSelection from "@/components/assignments/AircraftSelection.vue";
import AssignmentDrawer from "@/components/assignments/AssignmentDrawer.vue";
import AssignmentOverview from "@/components/assignments/AssignmentOverview.vue";
import AirportWeather from "@/components/flights/AirportWeather.vue";
import FlightManifest from "@/components/flights/FlightManifest.vue";
import SimBriefEditorDialog from "@/components/simbrief/SimBriefEditorDialog.vue";
import SimBriefGenerationDialog from "@/components/simbrief/SimBriefGenerationDialog.vue";
import SimBriefPlanningOptions from "@/components/simbrief/SimBriefPlanningOptions.vue";
import { useSimBriefAttempt } from "@/components/simbrief/useSimBriefAttempt";
import SimBriefPlanning from "@/pages/Ofp/Simbrief/Planning.vue";
import PvFlightInfo from "@/components/flights/PvFlightInfo.vue";
import PvLoadingState from "@/shared/components/PvLoadingState.vue";
import type { DispatchPayload } from "@/components/assignments/types";
import { useAssignmentDrawer } from "@/components/assignments/useAssignmentDrawer";

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
  simbriefEnabled: false,
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
  airline: { icao: "PVA", name: "phpVMS Air", logo: null },
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
  ofpPlanningUrl: "/ofp/planning?flight_id=flight-1",
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
    ofpGenerated: false,
    ofpPlanningUrl: "/ofp/planning?bid_id=9",
    ofpUrl: null,
  };
}

function planningData(): App.Http.Data.SimBriefPlanningData {
  return {
    attempt: {
      staticId: "attempt-1",
      flightId: flight.summary.id,
      aircraftId: aircraft.id,
      expiresAt: "2026-08-12T17:00:00Z",
      state: "ready",
    },
    flight,
    aircraft,
    providerFields: {
      acdata: '{"paxwgt":185}',
      airline: "PVA",
      altn: "AUTO",
      callsign: "PVA104",
      contpct: "0.05/5",
      dest: "KORD",
      etops: 0,
      find_sidstar: "R",
      firnot: 0,
      fl: "360",
      fltnum: "104",
      maps: "detail",
      navlog: 1,
      notams: 1,
      orig: "KDFW",
      planformat: "lido",
      reg: "N104PV",
      resvrule: 30,
      route: "MRF JALTU",
      static_id: "attempt-1",
      stepclimbs: 0,
      tlr: 1,
      type: "A320",
      units: "LBS",
    },
    callsignEditable: true,
    callsignOptions: ["PVA104", "PVA900"],
    requiresExplicitGeneration: true,
    embedGenerationAllowed: true,
  };
}

const buttonStub = defineComponent({
  inheritAttrs: false,
  props: { block: Boolean, disabled: Boolean, loading: Boolean, type: String, ui: Object },
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
  props: { handle: Boolean, open: Boolean },
  emits: ["update:open"],
  template: '<div class="drawer-stub" :data-open="open"><slot name="content" /></div>',
});
const aircraftSelectionStub = defineComponent({
  props: ["dispatchUrl", "subfleets", "aircraftId", "initialAircraft"],
  emits: ["update:aircraft-id"],
  template:
    '<button class="choose-aircraft" @click="$emit(\'update:aircraft-id\', 42)">Choose aircraft</button>',
});
const selectMenuStub = defineComponent({
  props: { id: String, items: { type: Array, default: () => [] }, modelValue: Number },
  emits: ["update:modelValue"],
  template:
    '<div :data-select="id"><button v-for="item in items" :key="item.id" :data-value="item.id" @click="$emit(\'update:modelValue\', item.id)">{{ item.displayName ?? item.registration }}</button></div>',
});
const formFieldStub = defineComponent({
  template: "<label><span><slot name=label /></span><slot /></label>",
});
const inputStub = defineComponent({
  props: { modelValue: String, disabled: Boolean },
  emits: ["update:modelValue"],
  template:
    '<input :value="modelValue" :disabled="disabled" @input="$emit(\'update:modelValue\', $event.target.value)">',
});
const selectStub = defineComponent({
  props: { modelValue: String, items: { type: Array, default: () => [] } },
  emits: ["update:modelValue"],
  template:
    '<select :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option v-for="item in items" :key="item.value" :value="item.value">{{ item.label }}</option></select>',
});
const textareaStub = defineComponent({
  props: { modelValue: String },
  emits: ["update:modelValue"],
  template:
    '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
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
    const state = useAssignmentDrawer();

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
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(new Response(JSON.stringify(dispatchPayload()), { status: 200 }))
      .mockImplementationOnce(
        () =>
          new Promise<Response>((resolve) => {
            confirmResponse = resolve;
          }),
      );
    vi.stubGlobal("fetch", fetchMock);
    const state = useAssignmentDrawer();
    state.show(summary.id);
    await flushPromises();
    state.selectedAircraftId.value = aircraft.id;
    const request = state.submit();

    expect(state.state.value).toBe("submitting");
    expect(state.payload.value?.selection).toBeNull();
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      "/flights/flight-1/bid",
      expect.objectContaining({ body: JSON.stringify({ aircraftId: aircraft.id }) }),
    );
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
    const state = useAssignmentDrawer();
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
    const state = useAssignmentDrawer();
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
    const state = useAssignmentDrawer();
    state.show(summary.id);
    await flushPromises();

    await state.submit();
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(state.failure.value?.type).toBe("validation");
    expect(state.state.value).toBe("selecting");
  });
});

describe("flight bid UI", () => {
  it("renders a shared progress circle with optional updateable text", async () => {
    const wrapper = mount(PvLoadingState, { props: { text: "Loading aircraft" } });

    expect(wrapper.get('[role="status"]').attributes("aria-label")).toBe("Loading aircraft");
    expect(wrapper.get(".pv-loading-state__spinner").attributes("aria-hidden")).toBe("true");
    expect(wrapper.text()).toBe("Loading aircraft");

    await wrapper.setProps({ text: "Checking availability" });
    expect(wrapper.text()).toBe("Checking availability");

    await wrapper.setProps({ text: undefined });
    expect(wrapper.text()).toBe("");
    expect(wrapper.get('[role="status"]').attributes("aria-label")).toBe("Loading");
  });

  it("renders an inline, sized flight identity with an optional link", () => {
    const wrapper = mount(PvFlightInfo, {
      props: {
        callsign: "PVA104",
        departure: "KDFW",
        arrival: "KORD",
        href: "/flights/flight-1",
        size: "lg",
      },
      global: {
        stubs: { Link: { props: ["href"], template: '<a :href="href"><slot /></a>' } },
      },
    });

    const info = wrapper.get(".pv-flight-info");
    expect(info.classes()).toContain("pv-flight-info--lg");
    expect(info.attributes("href")).toBe("/flights/flight-1");
    expect(info.text()).toContain("PVA104");
    expect(info.text()).toContain("KDFW→toKORD");
  });

  it("uses the same generic searchable subfleet selector for required and optional bids", () => {
    const required = mount(AircraftSelection, {
      props: {
        dispatchUrl: "/flights/flight-1/dispatch",
        subfleets: [subfleet],
        aircraftId: null,
        required: true,
        selectionVersion: 0,
      },
      global: { stubs: { USelectMenu: true } },
    });
    expect(required.text()).toContain("Eligible subfleet");
    expect(required.text()).toContain("Select the subfleet and aircraft to fly with.");

    const optional = mount(AircraftSelection, {
      props: {
        dispatchUrl: "/flights/flight-1/dispatch",
        subfleets: [subfleet],
        aircraftId: null,
        required: false,
        selectionVersion: 0,
      },
      global: { stubs: { USelectMenu: true } },
    });
    expect(optional.text()).toContain("Select the subfleet and aircraft to fly with.");
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
    const wrapper = mount(AssignmentDrawer, {
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
    expect(wrapper.getComponent(drawerStub).props("handle")).toBe(false);

    loadResponse?.(new Response(JSON.stringify(dispatchPayload()), { status: 200 }));
    await flushPromises();
    expect(wrapper.text()).toContain("Eligible subfleet");
    const select = wrapper.findAll("button").find((button) => button.text() === "Select");
    expect(select?.attributes()).toHaveProperty("disabled");
  });

  it("keeps the confirmed overview focused on route and bid facts without a map", () => {
    const wrapper = mount(AssignmentOverview, {
      props: { selection: selection() },
      global: {
        stubs: {
          UButton: buttonStub,
          PvSlot: true,
          NavDisplay: { template: '<div class="route-map" />' },
        },
      },
    });

    expect(wrapper.text()).toContain("Scheduled departure");
    expect(wrapper.get('[aria-label="Aircraft"]').text()).toContain("N104PV · A320");
    expect(wrapper.get('[aria-label="Aircraft"]').text()).toContain("A320-200 · KDFW");
    expect(wrapper.findAll("dt").map((fact) => fact.text())).not.toContain("Aircraft");
    expect(wrapper.text()).not.toContain("Choose an aircraft");
    expect(wrapper.find(".route-map").exists()).toBe(false);
  });

  it("keeps a labelled empty aircraft card when a bid has no aircraft", () => {
    const withoutAircraft = { ...selection(), aircraft: null };
    const wrapper = mount(AssignmentOverview, {
      props: { selection: withoutAircraft },
      global: { stubs: { UButton: buttonStub, PvSlot: true } },
    });

    expect(wrapper.get('[aria-label="Aircraft"]').text()).toContain("Aircraft not selected");
  });

  it("uses the same card content for the selected aircraft in the picker", () => {
    const wrapper = mount(AircraftCard, {
      props: { aircraft, label: "Selected aircraft" },
    });

    expect(wrapper.get('[aria-label="Selected aircraft"]').text()).toContain("N104PV · A320");
    expect(wrapper.get('[aria-label="Selected aircraft"]').text()).toContain("A320-200 · KDFW");
  });

  it("replaces selected menus with editable cards and restores them on edit", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(() =>
        Promise.resolve(new Response(JSON.stringify({ aircraft: [aircraft] }), { status: 200 })),
      ),
    );
    const wrapper = mount(AircraftSelection, {
      props: {
        dispatchUrl: "/flights/flight-1/dispatch",
        subfleets: [subfleet],
        aircraftId: aircraft.id,
        initialAircraft: aircraft,
        required: true,
        selectionVersion: 0,
      },
      global: { stubs: { UButton: buttonStub, USelectMenu: selectMenuStub } },
    });

    expect(wrapper.find('[data-select="bid-subfleet"]').exists()).toBe(false);
    expect(wrapper.find('[data-select="bid-aircraft"]').exists()).toBe(false);
    expect(wrapper.get('[aria-label="Selected subfleet"]').text()).toContain("PVA A320-200");
    expect(wrapper.get('[aria-label="Selected aircraft"]').text()).toContain("N104PV");

    const editButtons = wrapper.findAll("button").filter((button) => button.text() === "Edit");
    await editButtons[0]?.trigger("click");
    expect(wrapper.find('[data-select="bid-subfleet"]').exists()).toBe(true);
    expect(wrapper.text()).toContain("Cancel");
    await wrapper
      .findAll("button")
      .find((button) => button.text() === "Cancel")
      ?.trigger("click");
    expect(wrapper.find('[data-select="bid-subfleet"]').exists()).toBe(false);

    await wrapper
      .findAll("button")
      .filter((button) => button.text() === "Edit")[1]
      ?.trigger("click");
    await flushPromises();
    expect(wrapper.find('[data-select="bid-aircraft"]').exists()).toBe(true);
    await wrapper
      .findAll("button")
      .find((button) => button.text() === "Cancel")
      ?.trigger("click");
    expect(wrapper.find('[data-select="bid-aircraft"]').exists()).toBe(false);
  });

  it("clears the aircraft when the selected subfleet changes", async () => {
    const secondSubfleet = { ...subfleet, id: 5, displayName: "PVA A321-200" };
    vi.stubGlobal(
      "fetch",
      vi.fn(() => Promise.resolve(new Response(JSON.stringify({ aircraft: [] }), { status: 200 }))),
    );
    const wrapper = mount(AircraftSelection, {
      props: {
        dispatchUrl: "/flights/flight-1/dispatch",
        subfleets: [subfleet, secondSubfleet],
        aircraftId: aircraft.id,
        initialAircraft: aircraft,
        required: true,
        selectionVersion: 0,
      },
      global: { stubs: { UButton: buttonStub, USelectMenu: selectMenuStub } },
    });

    await wrapper
      .findAll("button")
      .find((button) => button.text() === "Edit")
      ?.trigger("click");
    await wrapper.get('[data-select="bid-subfleet"] [data-value="5"]').trigger("click");
    await flushPromises();

    expect(wrapper.emitted("update:aircraftId")?.at(-1)).toEqual([null]);
    expect(wrapper.get('[aria-label="Selected subfleet"]').text()).toContain("PVA A321-200");
    expect(wrapper.find('[aria-label="Selected aircraft"]').exists()).toBe(false);
    expect(wrapper.find('[data-select="bid-aircraft"]').exists()).toBe(true);
  });

  it("uses only the server-provided OFP action URL", async () => {
    const visit = vi.spyOn(router, "visit").mockImplementation(() => undefined as never);
    const generated = selection();
    generated.ofpGenerated = true;
    generated.ofpPlanningUrl = "/ofp/planning?bid_id=9";
    generated.ofpUrl = "/ofp/briefings/ofp-9";

    const wrapper = mount(AssignmentOverview, {
      props: { selection: generated },
      global: { stubs: { UButton: buttonStub, PvSlot: true } },
    });

    expect(wrapper.text()).toContain("View OFP");
    expect(wrapper.text()).not.toContain("Generate OFP");
    await wrapper.get("button").trigger("click");
    expect(visit).toHaveBeenCalledWith("/ofp/briefings/ofp-9");
  });

  it("visits the server-provided planning URL when OFP generation is enabled", async () => {
    const visit = vi.spyOn(router, "visit").mockImplementation(() => undefined as never);
    const pending = selection();
    pending.policy = { ...pending.policy, simbriefEnabled: true };

    const wrapper = mount(AssignmentOverview, {
      props: { selection: pending },
      global: { stubs: { UButton: buttonStub, PvSlot: true } },
    });

    expect(wrapper.text()).toContain("Generate OFP");
    expect(wrapper.text()).not.toContain("View OFP");
    await wrapper.get("button").trigger("click");
    expect(visit).toHaveBeenCalledWith("/ofp/planning?bid_id=9");
  });

  it("hides the OFP action when SimBrief is disabled", () => {
    const unavailable = selection();

    const wrapper = mount(AssignmentOverview, {
      props: { selection: unavailable },
      global: { stubs: { UButton: buttonStub, PvSlot: true } },
    });

    expect(wrapper.text()).not.toContain("Generate OFP");
    expect(wrapper.text()).not.toContain("View OFP");
  });

  it("keeps flight-only aircraft selection in the Ready for SimBrief panel", async () => {
    const visit = vi.spyOn(router, "visit").mockImplementation(() => undefined as never);
    const wrapper = mount(SimBriefPlanning, {
      props: {
        planning: null,
        aircraftSelection: {
          flight,
          dispatchUrl: "/flights/flight-1/dispatch",
          planningUrl: "/ofp/planning?flight_id=flight-1",
          aircraftAssignmentUrl: null,
          subfleets: [subfleet],
        },
      },
      global: { stubs: { AircraftSelection: aircraftSelectionStub, UButton: buttonStub } },
    });

    expect(wrapper.text()).toContain("Scheduled departure");
    expect(wrapper.text()).toContain("14:00");
    expect(wrapper.text()).toContain("Scheduled arrival");
    expect(wrapper.text()).toContain("16:15");
    expect(wrapper.text()).toContain("MRF JALTU");
    expect(wrapper.getComponent(aircraftSelectionStub).props("dispatchUrl")).toBe(
      "/flights/flight-1/dispatch",
    );
    const generate = wrapper.get("button:not(.choose-aircraft)");
    expect(generate.attributes()).toHaveProperty("disabled");
    expect(wrapper.getComponent(buttonStub).props("block")).toBe(true);
    await wrapper.get(".choose-aircraft").trigger("click");
    expect(visit).toHaveBeenCalledWith("/ofp/planning?flight_id=flight-1&aircraft_id=42");
  });

  it("saves a bid aircraft before revisiting planning by bid identity", async () => {
    const visit = vi.spyOn(router, "visit").mockImplementation(() => undefined as never);
    vi.stubGlobal(
      "fetch",
      vi.fn(() =>
        Promise.resolve(
          new Response(
            JSON.stringify({
              selection: { ...selection(), ofpPlanningUrl: "/ofp/planning?bid_id=13" },
            }),
            { status: 200 },
          ),
        ),
      ),
    );
    const wrapper = mount(SimBriefPlanning, {
      props: {
        planning: null,
        aircraftSelection: {
          flight,
          dispatchUrl: flight.dispatchUrl,
          planningUrl: "/ofp/planning?bid_id=13",
          aircraftAssignmentUrl: "/flights/flight-1/bid",
          subfleets: [subfleet],
        },
      },
      global: { stubs: { AircraftSelection: aircraftSelectionStub, UButton: buttonStub } },
    });

    await wrapper.get(".choose-aircraft").trigger("click");
    await flushPromises();

    expect(fetch).toHaveBeenCalledWith(
      "/flights/flight-1/bid",
      expect.objectContaining({ method: "POST", body: JSON.stringify({ aircraftId: 42 }) }),
    );
    expect(visit).toHaveBeenCalledWith("/ofp/planning?bid_id=13");
  });

  it("keeps the aircraft assignment visible after planning is prepared", () => {
    const wrapper = mount(SimBriefPlanning, {
      props: {
        planning: planningData(),
        aircraftSelection: {
          flight,
          dispatchUrl: flight.dispatchUrl,
          planningUrl: "/ofp/planning?bid_id=9",
          aircraftAssignmentUrl: "/flights/flight-1/bid",
          subfleets: [subfleet],
        },
      },
      global: { stubs: { AircraftSelection: aircraftSelectionStub, UButton: buttonStub } },
    });

    const assignment = wrapper.getComponent(aircraftSelectionStub);
    expect(assignment.props("aircraftId")).toBe(aircraft.id);
    expect(assignment.props("initialAircraft")).toEqual(aircraft);
    expect(wrapper.get("button").attributes()).not.toHaveProperty("disabled");
  });

  it("ports every editable Seven option while keeping provider identity fields out of overrides", () => {
    const wrapper = mount(SimBriefPlanningOptions, {
      props: { planning: planningData() },
      global: {
        stubs: {
          UFormField: formFieldStub,
          UInput: inputStub,
          USelect: selectStub,
          UTextarea: textareaStub,
        },
      },
    });

    expect(wrapper.text()).toContain("American Airlines");
    const optionValues = (name: string) =>
      wrapper
        .get(`select[name="${name}"]`)
        .findAll("option")
        .map((option) => option.attributes("value"));
    expect(optionValues("callsign")).toEqual(["PVA104", "PVA900"]);
    expect(optionValues("contpct")).toEqual([
      "0",
      "auto",
      "easa",
      "0.03/5",
      "0.03/10",
      "0.03/15",
      "0.05/5",
      "0.05/10",
      "0.05/15",
      "0.03",
      "0.05",
      "0.1",
      "0.15",
      "3",
      "5",
      "10",
      "15",
    ]);
    expect(optionValues("resvrule")).toEqual(["auto", "0", "15", "30", "45", "60", "75", "90"]);
    expect(optionValues("planformat")).toEqual([
      "lido",
      "aal",
      "aca",
      "afr",
      "afr2017",
      "awe",
      "baw",
      "ber",
      "dal",
      "dlh",
      "ein",
      "etd",
      "ezy",
      "gwi",
      "jbu",
      "jza",
      "klm",
      "qfa",
      "ryr",
      "swa",
      "thy",
      "uae",
      "ual",
      "ual f:wz",
    ]);
    expect(optionValues("maps")).toEqual(["detail", "simple", "none"]);
    wrapper.vm.generate();

    const overrides = wrapper.emitted("generate")?.[0]?.[0] as Record<string, string | null>;
    expect(overrides).toMatchObject({
      altn: "AUTO",
      route: "MRF JALTU",
      fl: "360",
      callsign: "PVA104",
      contpct: "0.05/5",
      resvrule: "30",
      find_sidstar: "R",
      stepclimbs: "0",
      etops: "0",
      planformat: "lido",
      units: "LBS",
      navlog: "1",
      tlr: "1",
      notams: "1",
      firnot: "0",
      maps: "detail",
    });
    expect(overrides).not.toHaveProperty("orig");
    expect(overrides).not.toHaveProperty("dest");
    expect(overrides).not.toHaveProperty("type");
    expect(overrides).not.toHaveProperty("acdata");
    expect(overrides.date).toMatch(/^\d{2}[A-Z]{3}\d{4}$/);
    expect(overrides.deph).toMatch(/^\d{2}$/);
    expect(overrides.depm).toMatch(/^\d{2}$/);
    expect(overrides.steh).toBe("2");
    expect(overrides.stem).toBe("15");
  });

  it("submits the planning form values when Generate OFP is pressed", async () => {
    const submit = vi
      .spyOn(HTMLFormElement.prototype, "submit")
      .mockImplementation(function (this: HTMLFormElement) {
        expect(new FormData(this).get("route")).toBe("MRF JALTU");
        expect(new FormData(this).get("planformat")).toBe("lido");
      });
    vi.stubGlobal(
      "fetch",
      vi.fn((_input: RequestInfo | URL, _init?: RequestInit) =>
        Promise.resolve(
          new Response(JSON.stringify({ apiCode: "api", providerUrl: "https://provider.test" }), {
            status: 200,
          }),
        ),
      ),
    );
    const open = vi.spyOn(window, "open").mockReturnValue({
      closed: false,
      close: vi.fn(),
      document,
      focus: vi.fn(),
    } as unknown as Window);
    const wrapper = mount(SimBriefPlanning, {
      props: { planning: planningData(), aircraftSelection: null },
      global: {
        stubs: {
          UButton: buttonStub,
          UFormField: formFieldStub,
          UInput: inputStub,
          USelect: selectStub,
          UTextarea: textareaStub,
        },
      },
    });

    await wrapper.get(".planning-actions button").trigger("click");
    await flushPromises();

    expect(submit).toHaveBeenCalledOnce();
    wrapper.unmount();
    submit.mockRestore();
    open.mockRestore();
  });

  it("disables and omits the flight level when planning stepclimbs", async () => {
    const wrapper = mount(SimBriefPlanningOptions, {
      props: { planning: planningData() },
      global: {
        stubs: {
          UFormField: formFieldStub,
          UInput: inputStub,
          USelect: selectStub,
          UTextarea: textareaStub,
        },
      },
    });

    await wrapper.get('select[name="stepclimbs"]').setValue("1");
    expect(wrapper.get('input[name="fl"]').attributes()).toHaveProperty("disabled");
    wrapper.vm.generate();
    expect(wrapper.emitted("generate")?.[0]?.[0]).toMatchObject({ stepclimbs: "1", fl: null });
  });

  it("submits provider fields and immediately polls after popup generation", async () => {
    const submit = vi
      .spyOn(HTMLFormElement.prototype, "submit")
      .mockImplementation(function (this: HTMLFormElement) {
        expect(new FormData(this).get("route")).toBe("DIRECT");
        expect(new FormData(this).get("maps")).toBe("none");
        expect(new FormData(this).has("fl")).toBe(false);
        expect(new FormData(this).get("orig")).toBe("KDFW");
        expect(new FormData(this).get("type")).toBe("A320");
      });
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ apiCode: "api", providerUrl: "https://provider.test" }), {
          status: 200,
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ briefingUrl: "/ofp/briefings/9" }), { status: 200 }),
      );
    vi.stubGlobal("fetch", fetchMock);
    vi.spyOn(window, "open").mockReturnValue({
      closed: false,
      close: vi.fn(),
      document,
      focus: vi.fn(),
    } as unknown as Window);
    const visit = vi.spyOn(router, "visit").mockImplementation(() => undefined as never);
    const attempt = useSimBriefAttempt(planningData());

    await attempt.generate({ route: "DIRECT", maps: "none", stepclimbs: "1", fl: null });
    await flushPromises();
    expect(submit).toHaveBeenCalledOnce();
    expect(fetchMock).toHaveBeenNthCalledWith(
      1,
      "/ofp/attempts/attempt-1/api-code",
      expect.objectContaining({
        body: expect.any(String),
      }),
    );
    expect(
      JSON.parse(String((fetchMock.mock.calls[0] as [RequestInfo | URL, RequestInit])[1].body)),
    ).toEqual({
      apiRequest: expect.any(String),
    });
    expect(fetchMock).toHaveBeenNthCalledWith(
      2,
      "/ofp/attempts/attempt-1/poll",
      expect.objectContaining({ method: "POST" }),
    );
    expect(visit).toHaveBeenCalledWith("/ofp/briefings/9");
    expect(attempt.state.value).toBe("complete");
    attempt.dispose();
    submit.mockRestore();
    visit.mockRestore();
  });

  it("submits modal generation into its iframe and begins polling from the submission signal", async () => {
    const submit = vi
      .spyOn(HTMLFormElement.prototype, "submit")
      .mockImplementation(function (this: HTMLFormElement) {
        expect(this.target).toBe("phpvms-simbrief-embedded");
        expect(new FormData(this).get("orig")).toBe("KDFW");
      });
    const wrapper = mount(SimBriefGenerationDialog, {
      props: {
        failure: null,
        flightLabel: "PVA104 · KDFW → KORD",
        open: true,
        requesting: false,
        submission: null,
      },
      global: { stubs: { UModal: drawerStub, UButton: buttonStub, UAlert: true } },
    });

    await wrapper.setProps({
      submission: { providerUrl: "https://provider.test", fields: { orig: "KDFW" } },
    });
    await flushPromises();
    expect(submit).toHaveBeenCalledOnce();
    expect(wrapper.emitted("submitted")).toHaveLength(1);
    expect(wrapper.get("iframe").attributes("name")).toBe("phpvms-simbrief-embedded");
    submit.mockRestore();
  });

  it("keeps aircraft loading generic and delegates lifecycle actions through slots", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(() =>
        Promise.resolve(new Response(JSON.stringify(dispatchPayload()), { status: 200 })),
      ),
    );

    const wrapper = mount(AircraftSelection, {
      props: {
        dispatchUrl: "/flights/flight-1/dispatch",
        subfleets: [subfleet],
        aircraftId: null,
        required: true,
        selectionVersion: 0,
      },
      global: { stubs: { USelectMenu: true } },
      slots: { actions: '<button class="caller-action">Continue</button>' },
    });

    await flushPromises();
    expect(wrapper.text()).not.toContain("SimBrief");
    expect(wrapper.find(".caller-action").text()).toBe("Continue");
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
    expect(wrapper.findAllComponents(buttonStub).some((button) => button.props("block"))).toBe(
      true,
    );
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
