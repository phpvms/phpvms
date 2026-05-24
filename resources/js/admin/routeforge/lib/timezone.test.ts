import { describe, expect, it } from "vitest";

import { computeArrTime } from "./timezone";

describe("computeArrTime", () => {
  const genDate = new Date(2026, 5, 15); // June 15 2026 — non-DST-boundary day

  it("returns the expected destination-local arrival across US timezones", () => {
    // KSFO (Pacific) 08:00 → KJFK (Eastern) with 5h 30m block.
    // Pacific is UTC-7 in June, Eastern UTC-4 → +3h offset → 08:00 + 5:30 + 3:00 = 16:30 local.
    const result = computeArrTime("08:00", "America/Los_Angeles", "America/New_York", 330, genDate);
    expect(result.arr_local).toBe("16:30");
    expect(result.day_shift).toBe(0);
    expect(result.fallback).toBe(false);
  });

  it("computes a positive day_shift for transpacific eastbound that crosses midnight", () => {
    // LAX (Pacific) 22:00 → NRT (Asia/Tokyo) 11h block: UTC-7 → UTC+9 = +16h.
    // 22:00 + 11h + 16h = 49h offset → day_shift = 2.
    const result = computeArrTime("22:00", "America/Los_Angeles", "Asia/Tokyo", 660, genDate);
    expect(result.fallback).toBe(false);
    expect(result.day_shift).toBeGreaterThanOrEqual(1);
  });

  it("falls back to naive math when destination timezone is null", () => {
    const result = computeArrTime("08:00", "America/Los_Angeles", null, 330, genDate);
    // Naive: 08:00 + 330 min = 13:30, no tz shift.
    expect(result.arr_local).toBe("13:30");
    expect(result.day_shift).toBe(0);
    expect(result.fallback).toBe(true);
  });

  it("falls back to naive math when origin timezone is null", () => {
    const result = computeArrTime("08:00", null, "Europe/London", 60, genDate);
    expect(result.fallback).toBe(true);
    expect(result.arr_local).toBe("09:00");
  });

  it("counts a day_shift when naive arithmetic crosses midnight", () => {
    const result = computeArrTime("23:00", null, null, 120, genDate);
    expect(result.fallback).toBe(true);
    expect(result.arr_local).toBe("01:00");
    expect(result.day_shift).toBe(1);
  });

  it("returns naive fallback for unparseable HH:MM input", () => {
    const result = computeArrTime(
      "not-a-time",
      "America/Los_Angeles",
      "America/New_York",
      60,
      genDate,
    );
    expect(result.fallback).toBe(true);
    expect(result.arr_local).toBe("00:00");
  });

  it("resolves DST correctly when generating before the bundle window starts", () => {
    // Same KSFO 08:00 → KJFK 5h30 block but generated on a January date
    // (PST = UTC-8, EST = UTC-5 → +3h offset, same as in summer).
    // Result should still be 16:30 EST.
    const winter = new Date(2026, 0, 15); // January 15
    const result = computeArrTime("08:00", "America/Los_Angeles", "America/New_York", 330, winter);
    expect(result.arr_local).toBe("16:30");
  });
});
