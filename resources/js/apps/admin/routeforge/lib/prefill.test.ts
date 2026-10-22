import { describe, expect, it } from "vitest";

import { applyTopology } from "../components/TopologyPicker";
import { defaultForm } from "../state/store";
import type { AirportSummary, Form, RouteForgeServerConfig } from "../state/types";
import { generate, type GenerateInput } from "./generator";
import { applyPrefill, parsePrefill } from "./prefill";

function airport(id: string, lat: number, lon: number): AirportSummary {
  return {
    id,
    icao: id,
    iata: null,
    name: id,
    country: null,
    region: null,
    location: null,
    lat,
    lon,
    timezone: "America/New_York",
    hub: false,
    elevation: null,
  };
}

const AIRPORTS = [
  airport("KJFK", 40.64, -73.78),
  airport("KBOS", 42.36, -71.01),
  airport("KORD", 41.97, -87.9),
  airport("KLAX", 33.94, -118.41),
];

/** Store defaults plus the minimum a tour needs to generate rows. */
function tourReadyForm(): Form {
  return {
    ...defaultForm(),
    airline_id: 1,
    origins: ["KJFK", "KBOS", "KORD", "KLAX"],
  };
}

function buildInput(form: Form): GenerateInput {
  const serverConfig: RouteForgeServerConfig = {
    cruise_speed_kt: 450,
    climb_descent_buffer: 20,
  };
  return {
    form,
    airports: new Map(AIRPORTS.map((a) => [a.id, a])),
    subfleets: new Map(),
    options: { gen_date: new Date(2026, 5, 15), server_config: serverConfig },
  };
}

describe("parsePrefill", () => {
  it("returns null for an absent, empty or unparseable attribute", () => {
    expect(parsePrefill(undefined)).toBeNull();
    expect(parsePrefill("")).toBeNull();
    expect(parsePrefill("not json")).toBeNull();
    expect(parsePrefill("{}")).toBeNull();
  });

  it("reads a full bundle-page deep link", () => {
    const prefill = parsePrefill(
      JSON.stringify({ topology: "tour", bundle_id: 12, bundle_name: "Pacific Tour", fresh: true }),
    );

    expect(prefill).toEqual({
      topology: "tour",
      bundle_id: 12,
      bundle_name: "Pacific Tour",
      fresh: true,
    });
  });

  it("drops an unknown topology and a non-integer bundle id", () => {
    expect(parsePrefill(JSON.stringify({ topology: "spiral", bundle_id: 3 }))).toEqual({
      topology: null,
      bundle_id: 3,
      bundle_name: "",
      fresh: false,
    });
    expect(parsePrefill(JSON.stringify({ topology: "tour", bundle_id: "12; drop" }))).toEqual({
      topology: "tour",
      bundle_id: null,
      bundle_name: "",
      fresh: false,
    });
    expect(parsePrefill(JSON.stringify({ topology: "spiral", bundle_id: 0 }))).toBeNull();
  });
});

describe("applyPrefill", () => {
  it("produces the same rows as choosing tour in the topology picker", () => {
    const prefill = parsePrefill(JSON.stringify({ topology: "tour", bundle_id: 12 }));
    if (prefill === null) {
      throw new Error("prefill should parse");
    }

    const deepLinked = generate(buildInput(applyPrefill(tourReadyForm(), prefill)));
    const picked = generate(buildInput(applyTopology(tourReadyForm(), "tour")));

    expect(deepLinked).toEqual(picked);
    expect(deepLinked.map((r) => r.route_leg)).toEqual(["1", "2", "3"]);
  });

  it("derives the flight-number strategy rather than leaving the store default", () => {
    // The trap this whole module exists to avoid: writing topology + mode
    // straight into the store keeps `sequential`, and the tour gets no legs.
    const prefill = parsePrefill(JSON.stringify({ topology: "tour" }));
    if (prefill === null) {
      throw new Error("prefill should parse");
    }

    const form = applyPrefill(defaultForm(), prefill);

    expect(form.mode).toBe("tour");
    expect(form.create_returns).toBe(false);
    expect(form.flight_number_strategy).toEqual({
      kind: "same_number_incrementing_legs",
      base: 100,
      base_leg: 1,
    });
  });

  it("locks the form onto the linked bundle", () => {
    const prefill = parsePrefill(
      JSON.stringify({ bundle_id: 12, bundle_name: "Pacific Tour", fresh: true }),
    );
    if (prefill === null) {
      throw new Error("prefill should parse");
    }

    const form = applyPrefill(defaultForm(), prefill);

    expect(form.bundle.existing_bundle_id).toBe(12);
    expect(form.bundle.name).toBe("Pacific Tour");
    // No topology in the link — the form keeps its default.
    expect(form.topology).toBe("hub_spokes");
  });
});
