import { afterEach, describe, expect, it } from "vitest";

import { defaultForm, form, rows } from "../state/store";
import type { Row } from "../state/types";
import { buildLintPayload } from "./lifecycle";

/**
 * Only the envelope gate is under test here — row fidelity is covered by
 * generator.test.ts / prefill.test.ts — so one minimal row is enough to
 * stand in for "the preview has rows".
 */
const ROW = {
  airline_id: 1,
  flight_number: 100,
  route_code: null,
  route_leg: "1",
  dpt_airport_id: "KJFK",
  arr_airport_id: "KBOS",
  edited: false,
} as unknown as Row;

afterEach(() => {
  form.value = defaultForm();
  rows.value = [];
});

describe("buildLintPayload destinations gate", () => {
  it("builds a payload for a tour with no destinations", () => {
    form.value = {
      ...defaultForm(),
      airline_id: 1,
      topology: "tour",
      mode: "tour",
      origins: ["KJFK", "KBOS"],
    };
    rows.value = [ROW];

    const payload = buildLintPayload();

    expect(payload).not.toBeNull();
    expect(payload?.destinations).toEqual([]);
    expect(payload?.origins).toEqual(["KJFK", "KBOS"]);
  });

  it("still refuses a cartesian batch with no destinations", () => {
    form.value = {
      ...defaultForm(),
      airline_id: 1,
      origins: ["KJFK"],
    };
    rows.value = [ROW];

    expect(buildLintPayload()).toBeNull();
  });
});
