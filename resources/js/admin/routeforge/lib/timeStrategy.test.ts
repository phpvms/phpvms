import { describe, expect, it } from "vitest";

import { assignDepartureTimes } from "./timeStrategy";
import type { JitterConfig, Row, TimeStrategy } from "../state/types";

function jitter(enabled: boolean, minutes = 0, seed = 1): JitterConfig {
  return { enabled, minutes, seed };
}

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
    departure_time: "00:00",
    arrival_time: "00:00",
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

describe("assignDepartureTimes", () => {
  describe("fixed strategy", () => {
    it("assigns the base time to every row", () => {
      const rows = [
        makeRow({ index: 0, dpt_airport_id: "KSFO" }),
        makeRow({ index: 1, dpt_airport_id: "KLAX" }),
        makeRow({ index: 2, dpt_airport_id: "KORD" }),
      ];
      const strategy: TimeStrategy = { kind: "fixed", base_time: "08:00", jitter: jitter(false) };

      const out = assignDepartureTimes(rows, strategy);

      expect(out.map((r) => r.departure_time)).toEqual(["08:00", "08:00", "08:00"]);
    });
  });

  describe("spread strategy", () => {
    it("distributes rows from the same origin at intervals", () => {
      const rows = [
        makeRow({ index: 0, dpt_airport_id: "KSFO" }),
        makeRow({ index: 1, dpt_airport_id: "KSFO" }),
        makeRow({ index: 2, dpt_airport_id: "KSFO" }),
        makeRow({ index: 3, dpt_airport_id: "KSFO" }),
      ];
      const strategy: TimeStrategy = {
        kind: "spread",
        base_time: "08:00",
        interval_minutes: 60,
        jitter: jitter(false),
      };

      const out = assignDepartureTimes(rows, strategy);

      expect(out.map((r) => r.departure_time)).toEqual(["08:00", "09:00", "10:00", "11:00"]);
    });

    it("slots independently per origin (mesh scenario)", () => {
      // 3 origins × 1 destination, spread @ 60min should give each origin
      // its own 08:00 slot (NOT three planes departing from different
      // origins all at 08:00, but each origin starts its own counter).
      const rows = [
        makeRow({ index: 0, dpt_airport_id: "KSFO" }),
        makeRow({ index: 1, dpt_airport_id: "KLAX" }),
        makeRow({ index: 2, dpt_airport_id: "KORD" }),
      ];
      const strategy: TimeStrategy = {
        kind: "spread",
        base_time: "08:00",
        interval_minutes: 60,
        jitter: jitter(false),
      };

      const out = assignDepartureTimes(rows, strategy);

      // Each origin gets slot 0 → 08:00. Distinct origins don't share counters.
      expect(out.map((r) => r.departure_time)).toEqual(["08:00", "08:00", "08:00"]);
    });
  });

  describe("banked strategy", () => {
    it("round-robins rows across the configured number of banks per origin", () => {
      const rows = [
        makeRow({ index: 0, dpt_airport_id: "KSFO" }),
        makeRow({ index: 1, dpt_airport_id: "KSFO" }),
        makeRow({ index: 2, dpt_airport_id: "KSFO" }),
        makeRow({ index: 3, dpt_airport_id: "KSFO" }),
      ];
      const strategy: TimeStrategy = {
        kind: "banked",
        base_time: "06:00",
        bank_count: 2,
        bank_spacing_minutes: 180,
        jitter: jitter(false),
      };

      const out = assignDepartureTimes(rows, strategy);

      // Slot 0 → bank 0 → 06:00; slot 1 → bank 1 → 09:00; slot 2 → bank 0; ...
      expect(out.map((r) => r.departure_time)).toEqual(["06:00", "09:00", "06:00", "09:00"]);
    });
  });

  describe("redeye strategy", () => {
    it("distributes rows evenly across the window starting at base", () => {
      const rows = [
        makeRow({ index: 0, dpt_airport_id: "KSFO" }),
        makeRow({ index: 1, dpt_airport_id: "KSFO" }),
        makeRow({ index: 2, dpt_airport_id: "KSFO" }),
      ];
      const strategy: TimeStrategy = {
        kind: "redeye",
        base_time: "22:00",
        window_minutes: 180, // 3h
        jitter: jitter(false),
      };

      const out = assignDepartureTimes(rows, strategy);

      // Step = 180 / 3 = 60min: 22:00, 23:00, 00:00 (wraps).
      expect(out[0]?.departure_time).toBe("22:00");
      expect(out[1]?.departure_time).toBe("23:00");
      expect(out[2]?.departure_time).toBe("00:00");
    });
  });

  describe("jitter modifier", () => {
    it("produces deterministic offsets for identical (rows, seed) inputs", () => {
      const rows = [makeRow({ index: 0 }), makeRow({ index: 1 }), makeRow({ index: 2 })];
      const strategy: TimeStrategy = {
        kind: "fixed",
        base_time: "08:00",
        jitter: jitter(true, 10, 42),
      };

      const a = assignDepartureTimes(rows, strategy).map((r) => r.departure_time);
      const b = assignDepartureTimes(rows, strategy).map((r) => r.departure_time);

      expect(a).toEqual(b);
    });

    it("changes offsets when the seed changes", () => {
      const rows = [makeRow({ index: 0 }), makeRow({ index: 1 })];
      const a = assignDepartureTimes(rows, {
        kind: "fixed",
        base_time: "08:00",
        jitter: jitter(true, 30, 1),
      }).map((r) => r.departure_time);
      const b = assignDepartureTimes(rows, {
        kind: "fixed",
        base_time: "08:00",
        jitter: jitter(true, 30, 2),
      }).map((r) => r.departure_time);

      // At least one row's offset differs between seed 1 and seed 2.
      expect(a).not.toEqual(b);
    });

    it("keeps offsets within ±N minutes of the base", () => {
      const rows = Array.from({ length: 50 }, (_, i) => makeRow({ index: i }));
      const strategy: TimeStrategy = {
        kind: "fixed",
        base_time: "12:00",
        jitter: jitter(true, 5, 99),
      };

      const out = assignDepartureTimes(rows, strategy);

      for (const r of out) {
        const [h, m] = r.departure_time.split(":").map((x) => Number.parseInt(x, 10)) as [
          number,
          number,
        ];
        const totalMins = h * 60 + m;
        // 12:00 = 720 mins. With jitter ±5, range is [715, 725].
        expect(totalMins).toBeGreaterThanOrEqual(715);
        expect(totalMins).toBeLessThanOrEqual(725);
      }
    });
  });
});
