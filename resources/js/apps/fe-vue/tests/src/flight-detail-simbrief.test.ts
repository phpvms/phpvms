import { mount } from "@vue/test-utils";
import { defineComponent, h } from "vue";
import { describe, expect, it } from "vitest";
import FlightDetailPanel from "@/features/flights/FlightDetailPanel.vue";

const buttonStub = defineComponent({
  props: { type: String, disabled: Boolean },
  setup(props, { slots }) {
    return () =>
      h("button", { type: props.type ?? "button", disabled: props.disabled }, slots.default?.());
  },
});

const flight: App.Http.Data.FlightDetailData = {
  summary: {
    id: "flight-1",
    callsign: "PVA104",
    dpt: "KDFW",
    arr: "KORD",
    distanceNm: 700,
    blockTime: "02:15",
    type: "J",
    airline: null,
    bidId: null,
    scheduledDeparture: "14:00",
    scheduledArrival: "16:15",
    routeCode: "A",
    availability: "available",
    availabilityReason: null,
    primaryAction: "bid",
  },
  departure: { id: "KDFW", icao: "KDFW", name: "Dallas", lat: 32.9, lon: -97 },
  arrival: { id: "KORD", icao: "KORD", name: "Chicago", lat: 41.9, lon: -87.9 },
  alternate: null,
  departureWeather: null,
  arrivalWeather: null,
  alternateWeather: null,
  scheduledDeparture: "14:00",
  scheduledArrival: "16:15",
  route: "MRF JALTU",
  cruiseLevel: 360,
  dispatchUrl: "/flights/flight-1/dispatch",
  simbriefPlanningUrl: "/simbrief/planning?flight_id=flight-1",
};

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
  simbriefAvailable: true,
  simbriefRequiresBid: false,
  simbriefBlocksAircraft: false,
};

describe("flight detail SimBrief entry", () => {
  it("uses the server-provided planning URL when SimBrief is allowed", () => {
    const wrapper = mount(FlightDetailPanel, {
      props: { flight, policy },
      global: {
        stubs: {
          UButton: buttonStub,
          Link: defineComponent({
            props: { href: String },
            template: '<a :href="href"><slot /></a>',
          }),
          PvSlot: true,
          FlightRouteMap: true,
          AirportWeather: true,
        },
      },
    });

    expect(wrapper.get('a[href="/simbrief/planning?flight_id=flight-1"]').text()).toBe(
      "Generate SimBrief",
    );
  });

  it("does not offer SimBrief when the server policy disallows it", () => {
    const wrapper = mount(FlightDetailPanel, {
      props: { flight, policy: { ...policy, simbriefAvailable: false } },
      global: {
        stubs: {
          UButton: buttonStub,
          Link: true,
          PvSlot: true,
          FlightRouteMap: true,
          AirportWeather: true,
        },
      },
    });

    expect(wrapper.text()).not.toContain("Generate SimBrief");
  });
});
