import { describe, expect, it } from "vitest";

import { assignFlightNumbers } from "./flightNumber";
import type { FlightNumberStrategy, Row } from "../state/types";

function makeRow(overrides: Partial<Row>): Row {
  return {
    index: 0,
    pair_index: 0,
    direction: "outbound",
    airline_id: 1,
    flight_number: 0,
    route_code: null,
    route_leg: null,
    dpt_airport_id: "KSFO",
    arr_airport_id: "KLAX",
    dpt_timezone: "America/Los_Angeles",
    arr_timezone: "America/Los_Angeles",
    departure_time: "08:00",
    arrival_time: "11:00",
    arr_day_shift: 0,
    distance_nm: 337,
    flight_time: 90,
    days_mask: 127,
    flight_type: null,
    enabled: true,
    edited: false,
    ...overrides,
  };
}

describe("assignFlightNumbers", () => {
  it("sequential strategy increments from the base", () => {
    const rows = [
      makeRow({ index: 0 }),
      makeRow({ index: 1 }),
      makeRow({ index: 2 }),
      makeRow({ index: 3 }),
    ];
    const strategy: FlightNumberStrategy = { kind: "sequential", base: 100 };

    const out = assignFlightNumbers(rows, strategy);

    expect(out.map((r) => r.flight_number)).toEqual([100, 101, 102, 103]);
  });

  it("even_odd_by_direction assigns outbound=even, return=odd within each pair", () => {
    const rows = [
      makeRow({ index: 0, pair_index: 0, direction: "outbound" }),
      makeRow({ index: 1, pair_index: 0, direction: "return" }),
      makeRow({ index: 2, pair_index: 1, direction: "outbound" }),
      makeRow({ index: 3, pair_index: 1, direction: "return" }),
    ];
    const strategy: FlightNumberStrategy = { kind: "even_odd_by_direction", base: 100 };

    const out = assignFlightNumbers(rows, strategy);

    expect(out.map((r) => r.flight_number)).toEqual([100, 101, 102, 103]);
  });

  it("even_outbound_only assigns the same number to both legs of a pair", () => {
    const rows = [
      makeRow({ index: 0, pair_index: 0, direction: "outbound" }),
      makeRow({ index: 1, pair_index: 0, direction: "return" }),
      makeRow({ index: 2, pair_index: 1, direction: "outbound" }),
      makeRow({ index: 3, pair_index: 1, direction: "return" }),
    ];
    const strategy: FlightNumberStrategy = { kind: "even_outbound_only", base: 100 };

    const out = assignFlightNumbers(rows, strategy);

    expect(out.map((r) => r.flight_number)).toEqual([100, 100, 102, 102]);
  });

  it("manual strategy preserves caller-supplied flight numbers verbatim", () => {
    const rows = [
      makeRow({ index: 0, flight_number: 999 }),
      makeRow({ index: 1, flight_number: 1234 }),
    ];
    const strategy: FlightNumberStrategy = { kind: "manual" };

    const out = assignFlightNumbers(rows, strategy);

    expect(out.map((r) => r.flight_number)).toEqual([999, 1234]);
  });

  it("does not mutate the input row list", () => {
    const rows = [makeRow({ index: 0 })];
    const before = JSON.parse(JSON.stringify(rows));

    assignFlightNumbers(rows, { kind: "sequential", base: 100 });

    expect(rows).toEqual(before);
  });
});
