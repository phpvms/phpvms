import { mount } from "@vue/test-utils";
import { describe, expect, it } from "vitest";
import PvFlightCard from "@/shared/components/PvFlightCard.vue";

function pirep(overrides: Partial<App.Http.Data.PirepData> = {}) {
  return {
    id: "p1",
    ident: "PVA104",
    aircraft: "B738 (N8901)",
    airline: "phpvms Air",
    dpt: "KAUS",
    arr: "KJFK",
    dptName: "Austin Bergstrom",
    arrName: "John F Kennedy Intl",
    state: "Accepted",
    status: null,
    flightTime: "02:15",
    distance: "1420 nmi",
    plannedFlightTime: null,
    plannedDistance: null,
  } as unknown as App.Http.Data.PirepData;
}

function flight(overrides: Partial<App.Http.Data.FlightDetailData> = {}) {
  return {
    summary: {
      id: "f1",
      callsign: "PVA220",
      dpt: "EGLL",
      arr: "LFPG",
      distanceNm: 214,
      blockTime: "01:10",
      airline: { icao: "PVA", name: "phpvms Air", logo: "/logo.png" },
      routeCode: "A",
      scheduledDeparture: "14:00",
      scheduledArrival: "15:10",
    },
    departure: { id: "1", icao: "EGLL", name: "Heathrow", lat: null, lon: null },
    arrival: { id: "2", icao: "LFPG", name: "Charles de Gaulle", lat: null, lon: null },
    scheduledDeparture: "14:00",
    scheduledArrival: "15:10",
    ...overrides,
  } as unknown as App.Http.Data.FlightDetailData;
}

function liveFlight(overrides: Partial<App.Http.Data.MapLiveFlightData> = {}) {
  return {
    pirepId: "l1",
    ident: "PVA900",
    status: "En route",
    phase: "CRZ",
    airline: { icao: "PVA", name: "Live Air", logo: "/live.png" },
    aircraft: { id: 3, registration: "N900PV", name: "A320" },
    dptAirport: { icao: "KSFO", name: "San Francisco Intl" },
    arrAirport: { icao: "KLAX", name: "Los Angeles Intl" },
    ...overrides,
  } as unknown as App.Http.Data.MapLiveFlightData;
}

describe("PvFlightCard source resolution", () => {
  it("renders a PIREP's route, ident and flown figures", () => {
    const text = mount(PvFlightCard, { props: { pirep: pirep() } }).text();

    expect(text).toContain("PVA104");
    expect(text).toContain("KAUS");
    expect(text).toContain("KJFK");
    expect(text).toContain("Austin Bergstrom");
    expect(text).toContain("02:15");
    expect(text).toContain("1420 nmi");
    expect(text).toContain("B738 (N8901)");
  });

  it("falls back to a PIREP's state when it carries no status", () => {
    const text = mount(PvFlightCard, { props: { pirep: pirep() } }).text();

    expect(text).toContain("Accepted");
  });

  it("renders a scheduled flight, formatting the raw distance as NM", () => {
    const text = mount(PvFlightCard, { props: { flight: flight() } }).text();

    expect(text).toContain("PVA220");
    expect(text).toContain("EGLL");
    expect(text).toContain("Heathrow");
    expect(text).toContain("14:00");
    expect(text).toContain("01:10");
    // distanceNm is a number on FlightListItemData, unlike the PIREP's string.
    expect(text).toContain("214 NM");
  });

  it("renders a live flight's airports and aircraft", () => {
    const text = mount(PvFlightCard, { props: { live_flight: liveFlight() } }).text();

    expect(text).toContain("PVA900");
    expect(text).toContain("KSFO");
    expect(text).toContain("Los Angeles Intl");
    expect(text).toContain("A320");
    expect(text).toContain("En route");
  });

  it("uses the aircraft registration when the live aircraft has no name", () => {
    const text = mount(PvFlightCard, {
      props: {
        live_flight: liveFlight({ aircraft: { id: 3, registration: "N900PV", name: null } }),
      },
    }).text();

    expect(text).toContain("N900PV");
  });

  it("prefers a PIREP over a scheduled flight when both are passed", () => {
    const text = mount(PvFlightCard, { props: { pirep: pirep(), flight: flight() } }).text();

    expect(text).toContain("PVA104");
    expect(text).not.toContain("PVA220");
  });

  it("lets an explicit airline prop override the flight's own airline", () => {
    const wrapper = mount(PvFlightCard, {
      props: {
        flight: flight(),
        airline: { name: "Override Air", icao: "OVR", iata: null, logo: "/override.png" },
      },
    });

    expect(wrapper.text()).toContain("Override Air");
    expect(wrapper.get("img").attributes("src")).toBe("/override.png");
  });

  it("hides the stats row when no source supplies any figure", () => {
    const wrapper = mount(PvFlightCard, {
      props: { live_flight: liveFlight({ aircraft: null }) },
    });

    expect(wrapper.text()).not.toContain("Flight time");
    expect(wrapper.text()).not.toContain("Distance");
  });
});
