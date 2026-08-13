import { describe, expect, it } from "vitest";

import { generate, type GenerateInput } from "./generator";
import type {
  AirportSummary,
  Form,
  Row,
  RouteForgeServerConfig,
  SubfleetSummary,
} from "../state/types";

function airport(
  id: string,
  lat = 37.6,
  lon = -122.4,
  tz: string | null = "America/Los_Angeles",
): AirportSummary {
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
    timezone: tz,
    hub: false,
    elevation: null,
  };
}

function subfleet(id: number, cruise = 450): SubfleetSummary {
  return {
    id,
    name: `Fleet ${id}`,
    type: `T${id}`,
    cruise_speed: cruise,
    max_range_nm: 4000,
    route_types: null,
    aircraft_count: 5,
  };
}

function baseForm(overrides: Partial<Form>): Form {
  return {
    airline_id: 1,
    topology: "hub_spokes",
    origins: ["KSFO"],
    destinations: ["KLAX"],
    mode: "cartesian",
    create_returns: false,
    subfleet_ids: [],
    flight_type: null,
    event_id: null,
    days_mask: 127,
    time_strategy: {
      kind: "fixed",
      base_time: "08:00",
      jitter: { enabled: false, minutes: 0, seed: 0 },
    },
    flight_number_strategy: { kind: "sequential", base: 100 },
    route_preset: "custom",
    frequency_preset: "custom",
    bundle: {
      existing_bundle_id: null,
      name: "Test",
      description: "",
      enabled: true,
      start_date: null,
      end_date: null,
      fare_multiplier: "",
      activate_on_save: true,
    },
    ...overrides,
  };
}

function buildInput(
  form: Form,
  airports: AirportSummary[],
  subfleets: SubfleetSummary[] = [],
): GenerateInput {
  const airportMap = new Map(airports.map((a) => [a.id, a]));
  const subfleetMap = new Map(subfleets.map((s) => [s.id, s]));
  const serverConfig: RouteForgeServerConfig = {
    cruise_speed_kt: 450,
    climb_descent_buffer: 20,
  };
  return {
    form,
    airports: airportMap,
    subfleets: subfleetMap,
    options: {
      gen_date: new Date(2026, 5, 15),
      server_config: serverConfig,
    },
  };
}

describe("generator.generate", () => {
  it("Hub→Spokes with single origin produces one row per destination", () => {
    const form = baseForm({
      origins: ["KSFO"],
      destinations: ["KLAX", "KSEA", "KPDX"],
    });
    const airports = [
      airport("KSFO"),
      airport("KLAX", 33.94, -118.41),
      airport("KSEA", 47.45, -122.31),
      airport("KPDX", 45.59, -122.6),
    ];

    const rows = generate(buildInput(form, airports));

    expect(rows).toHaveLength(3);
    expect(rows.map((r) => `${r.dpt_airport_id}→${r.arr_airport_id}`)).toEqual([
      "KSFO→KLAX",
      "KSFO→KSEA",
      "KSFO→KPDX",
    ]);
    rows.forEach((r) => expect(r.direction).toBe("outbound"));
  });

  it("Hub & Spokes with create_returns produces interleaved outbound + return", () => {
    const form = baseForm({
      origins: ["KSFO"],
      destinations: ["KLAX", "KSEA"],
      create_returns: true,
    });
    const airports = [
      airport("KSFO"),
      airport("KLAX", 33.94, -118.41),
      airport("KSEA", 47.45, -122.31),
    ];

    const rows = generate(buildInput(form, airports));

    expect(rows).toHaveLength(4);
    expect(rows.map((r) => `${r.dpt_airport_id}→${r.arr_airport_id}`)).toEqual([
      "KSFO→KLAX",
      "KLAX→KSFO",
      "KSFO→KSEA",
      "KSEA→KSFO",
    ]);
    expect(rows.map((r) => r.direction)).toEqual(["outbound", "return", "outbound", "return"]);
    // pair_index shared between outbound + return.
    expect(rows[0]?.pair_index).toBe(rows[1]?.pair_index);
  });

  it("Tour mode connects origins in order", () => {
    const form = baseForm({
      mode: "tour",
      origins: ["KSFO", "KLAX", "KPHX", "KDFW"],
      destinations: [],
    });
    const airports = [
      airport("KSFO"),
      airport("KLAX", 33.94, -118.41),
      airport("KPHX", 33.44, -112.01),
      airport("KDFW", 32.9, -97.04),
    ];

    const rows = generate(buildInput(form, airports));

    expect(rows).toHaveLength(3);
    expect(rows.map((r) => `${r.dpt_airport_id}→${r.arr_airport_id}`)).toEqual([
      "KSFO→KLAX",
      "KLAX→KPHX",
      "KPHX→KDFW",
    ]);
  });

  it("excludes self-loop rows where origin === destination", () => {
    const form = baseForm({
      origins: ["KSFO", "KLAX"],
      destinations: ["KSFO", "KLAX", "KORD"],
    });
    const airports = [
      airport("KSFO"),
      airport("KLAX", 33.94, -118.41),
      airport("KORD", 41.97, -87.9),
    ];

    const rows = generate(buildInput(form, airports));

    // 2 origins × 3 destinations = 6 cartesian, minus 2 self-loops (SFO→SFO, LAX→LAX) = 4.
    expect(rows).toHaveLength(4);
    rows.forEach((r) => expect(r.dpt_airport_id).not.toBe(r.arr_airport_id));
  });

  it("returns [] when airline_id is null", () => {
    const form = baseForm({ airline_id: null });
    const airports = [airport("KSFO"), airport("KLAX", 33.94, -118.41)];

    expect(generate(buildInput(form, airports))).toEqual([]);
  });

  it("computes a non-zero distance + flight_time for known pairs", () => {
    const form = baseForm({
      origins: ["KSFO"],
      destinations: ["KJFK"],
    });
    const airports = [airport("KSFO", 37.62, -122.38), airport("KJFK", 40.64, -73.78)];

    const rows = generate(buildInput(form, airports));

    expect(rows).toHaveLength(1);
    const row = rows[0] as Row;
    expect(row.distance_nm).toBeGreaterThan(2200);
    expect(row.distance_nm).toBeLessThan(2400);
    expect(row.flight_time).toBeGreaterThan(300); // 5h+ block time
  });

  it("picks the slowest cruise speed across selected subfleets (conservative scheduling)", () => {
    const form = baseForm({
      origins: ["KSFO"],
      destinations: ["KLAX"],
      subfleet_ids: [1, 2],
    });
    const airports = [airport("KSFO"), airport("KLAX", 33.94, -118.41)];
    const subfleets = [subfleet(1, 480), subfleet(2, 300)]; // min = 300

    const rows = generate(buildInput(form, airports, subfleets));

    // Distance ≈ 293 nm @ 300 kt → 58.6 min cruise + 20 buffer = ~79.
    const row = rows[0] as Row;
    expect(row.flight_time).toBeGreaterThan(70);
    expect(row.flight_time).toBeLessThan(90);
  });

  it("propagates airport timezone metadata onto each row (drives L11)", () => {
    const form = baseForm({
      origins: ["KSFO"],
      destinations: ["XXX1"], // unknown TZ
    });
    const airports = [airport("KSFO"), airport("XXX1", 0, 0, null)];

    const rows = generate(buildInput(form, airports));

    const row = rows[0] as Row;
    expect(row.dpt_timezone).toBe("America/Los_Angeles");
    expect(row.arr_timezone).toBeNull();
  });
});
