import { beforeAll, describe, expect, it } from "vitest";

import { canProceed, runLint, type LintContext } from "./lint";
import type { Row, SubfleetSummary } from "../state/types";

// happy-dom doesn't pre-populate window.routeforgeConfig. Stub it so the
// t() helper in lib/i18n.ts has a translations object to walk. Missing
// keys fall back to the raw dot-path string per i18n.ts contract; the
// tests don't assert on message text, only on rule IDs / severities /
// row_index attachment shape.
beforeAll(() => {
  (window as unknown as { routeforgeConfig: Record<string, unknown> }).routeforgeConfig = {
    csrf_token: "test",
    locale: "en",
    user: { id: 1, name: "Test", can_commit: true },
    airlines: [],
    bundles: [],
    routes: {
      preview_airports: "/x",
      subfleets: "/x",
      airline_stats: "/x",
      check_duplicates: "/x",
      lint: "/x",
      commit: "/x",
    },
    config: {},
    translations: {},
  };
});

function row(overrides: Partial<Row> = {}): Row {
  return {
    index: 0,
    pair_index: 0,
    direction: "outbound",
    airline_id: 1,
    flight_number: 100,
    route_code: null,
    route_leg: null,
    dpt_airport_id: "KSFO",
    arr_airport_id: "KLAX",
    dpt_timezone: "America/Los_Angeles",
    arr_timezone: "America/Los_Angeles",
    dpt_time: "08:00",
    arr_time: "11:00",
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

function subfleet(overrides: Partial<SubfleetSummary> = {}): SubfleetSummary {
  return {
    id: 1,
    name: "T1",
    type: "T1",
    cruise_speed: 450,
    max_range_nm: 4000,
    route_types: null,
    aircraft_count: 5,
    ...overrides,
  };
}

function makeCtx(overrides: Partial<LintContext>): LintContext {
  return {
    rows: [],
    selected_subfleets: [],
    flight_type: null,
    event: null,
    bundle_start_date: null,
    bundle_end_date: null,
    airline_stats: { existing_active_flights_count: 0, hub_airports: [] },
    ...overrides,
  };
}

describe("runLint catalog", () => {
  it("L1 fires when selected aircraft < row_count / 2", () => {
    const report = runLint(
      makeCtx({
        rows: Array.from({ length: 20 }, (_, i) => row({ index: i, flight_number: 100 + i })),
        selected_subfleets: [subfleet({ aircraft_count: 5 })],
      }),
    );

    const l1 = report.warnings.find((i) => i.rule === "L1");
    expect(l1).toBeDefined();
    expect(l1?.row_index).toBeNull();
  });

  it("L2 fires per-row when no selected subfleet covers the distance", () => {
    const report = runLint(
      makeCtx({
        rows: [row({ index: 0, distance_nm: 3500 }), row({ index: 1, distance_nm: 500 })],
        selected_subfleets: [subfleet({ max_range_nm: 1500 })],
      }),
    );

    const l2 = report.warnings.filter((i) => i.rule === "L2");
    expect(l2).toHaveLength(1);
    expect(l2[0]?.row_index).toBe(0);
  });

  it("L2 short-circuits when any selected subfleet has NULL range", () => {
    const report = runLint(
      makeCtx({
        rows: [row({ distance_nm: 9999 })],
        selected_subfleets: [subfleet({ max_range_nm: null })],
      }),
    );

    expect(report.warnings.filter((i) => i.rule === "L2")).toHaveLength(0);
  });

  it("L3 fires once when no subfleets are selected", () => {
    const report = runLint(
      makeCtx({
        rows: [row()],
        selected_subfleets: [],
      }),
    );

    expect(report.warnings.filter((i) => i.rule === "L3")).toHaveLength(1);
  });

  it("L4 emits an error for intra-batch duplicate on the strict 4-tuple", () => {
    const report = runLint(
      makeCtx({
        rows: [
          row({ index: 0, flight_number: 100 }),
          row({ index: 1, flight_number: 101 }),
          row({ index: 2, flight_number: 100 }), // collides with row 0
        ],
      }),
    );

    const l4 = report.errors.find((i) => i.rule === "L4");
    expect(l4).toBeDefined();
    expect(l4?.severity).toBe("error");
    expect(l4?.row_index).toBe(2);
  });

  it("L5 fires when existing_duplicates has a hit for the row", () => {
    const r = row({ index: 0, airline_id: 1, flight_number: 100 });
    const report = runLint(
      makeCtx({
        rows: [r],
        existing_duplicates: {
          "1|100|∅|∅": { existing_flight_id: "abc123", ident: "TST100" },
        },
      }),
    );

    const l5 = report.warnings.find((i) => i.rule === "L5");
    expect(l5).toBeDefined();
    expect(l5?.row_index).toBe(0);
  });

  it("L5 is a no-op when no existing_duplicates map is supplied", () => {
    const report = runLint(
      makeCtx({
        rows: [row()],
      }),
    );

    expect(report.warnings.filter((i) => i.rule === "L5")).toHaveLength(0);
  });

  it("L6 emits an error per self-loop row", () => {
    const report = runLint(
      makeCtx({
        rows: [
          row({ index: 0, dpt_airport_id: "KSFO", arr_airport_id: "KSFO" }),
          row({ index: 1, dpt_airport_id: "KSFO", arr_airport_id: "KLAX" }),
          row({ index: 2, dpt_airport_id: "KJFK", arr_airport_id: "KJFK" }),
        ],
      }),
    );

    const l6 = report.errors.filter((i) => i.rule === "L6");
    expect(l6).toHaveLength(2);
    expect(l6.map((i) => i.row_index)).toEqual([0, 2]);
  });

  it("L8 fires when bundle window doesn't overlap event window", () => {
    const report = runLint(
      makeCtx({
        rows: [row()],
        event: { id: 1, start_date: "2026-07-01", end_date: "2026-07-31" },
        bundle_start_date: "2026-08-01",
        bundle_end_date: "2026-08-31",
      }),
    );

    expect(report.warnings.filter((i) => i.rule === "L8")).toHaveLength(1);
  });

  it("L9 fires above the soft cap, L10 silent below the hard cap", () => {
    const report = runLint(
      makeCtx({
        rows: Array.from({ length: 75 }, (_, i) => row({ index: i, flight_number: 100 + i })),
      }),
    );

    expect(report.warnings.filter((i) => i.rule === "L9")).toHaveLength(1);
    expect(report.errors.filter((i) => i.rule === "L10")).toHaveLength(0);
  });

  it("L10 fires above the hard cap as an error", () => {
    const report = runLint(
      makeCtx({
        rows: Array.from({ length: 150 }, (_, i) => row({ index: i, flight_number: 100 + i })),
      }),
    );

    expect(report.errors.filter((i) => i.rule === "L10")).toHaveLength(1);
  });

  it("L11 fires per row when origin OR destination timezone is null", () => {
    const report = runLint(
      makeCtx({
        rows: [
          row({ index: 0, dpt_timezone: null }),
          row({ index: 1, arr_timezone: null }),
          row({ index: 2 }),
        ],
      }),
    );

    const l11 = report.warnings.filter((i) => i.rule === "L11");
    expect(l11).toHaveLength(2);
    expect(l11.map((i) => i.row_index)).toEqual([0, 1]);
  });

  it("canProceed gates on errors only", () => {
    const okReport = runLint(
      makeCtx({
        rows: [row()],
        selected_subfleets: [subfleet()],
      }),
    );
    expect(canProceed(okReport)).toBe(true);

    const blockedReport = runLint(
      makeCtx({
        rows: [row({ dpt_airport_id: "KSFO", arr_airport_id: "KSFO" })],
      }),
    );
    expect(canProceed(blockedReport)).toBe(false);
  });
});
