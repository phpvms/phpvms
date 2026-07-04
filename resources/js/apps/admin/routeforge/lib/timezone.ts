/**
 * Timezone-aware arrival time math (Decision 12).
 *
 * Uses `@date-fns/tz` (3 KB add-on to existing date-fns) to compute the
 * destination-local arrival from an origin-local departure + block time.
 * The client is the sole authority for this calculation — the server
 * persists `departure_time`, `arrival_time`, and `arr_day_shift` from the
 * commit payload verbatim, gated by `permission:edit:flight` and lint
 * validation. There is no parallel server-side recompute.
 *
 * Generation date for DST resolution: today in the application's configured
 * timezone, NOT the bundle's start_date. Using start_date would create
 * off-by-one DST drift when generating ahead of the schedule window.
 *
 * Naïve fallback path: when origin OR destination tz is null, add the block
 * time as raw minutes, wrap modulo 24h, count day shifts. Triggers L11 lint
 * downstream so the user sees the arrival time is informational, not accurate.
 */

import { TZDate } from "@date-fns/tz";

export type ArrivalTimeResult = {
  /** Destination-local HH:MM (zero-padded). */
  arr_local: string;
  /** +N calendar days from departure local date. Can be negative for transpacific eastbound. */
  day_shift: number;
  /** True when origin or destination tz was null and the naïve path ran. Drives L11. */
  fallback: boolean;
};

const MS_PER_MIN = 60_000;
const MS_PER_DAY = 86_400_000;
const MINS_PER_DAY = 1440;

/**
 * @param dptLocal     Origin-local departure as "HH:MM".
 * @param originTz     IANA tz of the origin airport, e.g. "America/Los_Angeles". Null falls back.
 * @param destTz       IANA tz of the destination airport. Null falls back.
 * @param flightTime   Block time in minutes (already includes climb/descent buffer).
 * @param genDate      Reference calendar date for DST resolution (today, in app tz).
 */
export function computeArrTime(
  dptLocal: string,
  originTz: string | null,
  destTz: string | null,
  flightTime: number,
  genDate: Date,
): ArrivalTimeResult {
  const [h, m] = parseHHMM(dptLocal);
  if (h === null || m === null) {
    return { arr_local: "00:00", day_shift: 0, fallback: true };
  }

  const fallback = originTz === null || destTz === null;
  if (fallback) {
    return naiveAdd(h, m, flightTime);
  }

  // TZ-aware: construct dep as origin-tz wallclock for genDate Y/M/D, add
  // block time as raw ms, then re-interpret in dest tz to read the local
  // wallclock and date. Day shift compares dest-local arrival date with the
  // origin-local departure date (= genDate).
  const y = genDate.getFullYear();
  const mo = genDate.getMonth();
  const d = genDate.getDate();

  const dep = new TZDate(y, mo, d, h, m, 0, 0, originTz);
  const arr = new TZDate(dep.getTime() + flightTime * MS_PER_MIN, destTz);

  const depMidnight = Date.UTC(y, mo, d);
  const arrMidnight = Date.UTC(arr.getFullYear(), arr.getMonth(), arr.getDate());
  const dayShift = Math.round((arrMidnight - depMidnight) / MS_PER_DAY);

  return {
    arr_local: fmtHHMM(arr.getHours(), arr.getMinutes()),
    day_shift: dayShift,
    fallback: false,
  };
}

/**
 * Naïve fallback: ignore TZ offsets, add block time as raw minutes, wrap.
 * Day shift = how many full 24h periods the arrival crossed past departure.
 */
function naiveAdd(h: number, m: number, flightTime: number): ArrivalTimeResult {
  const totalMins = h * 60 + m + flightTime;
  const wrapped = ((totalMins % MINS_PER_DAY) + MINS_PER_DAY) % MINS_PER_DAY;
  const dayShift = Math.floor(totalMins / MINS_PER_DAY);

  return {
    arr_local: fmtHHMM(Math.floor(wrapped / 60), wrapped % 60),
    day_shift: dayShift,
    fallback: true,
  };
}

function parseHHMM(s: string): [number, number] | [null, null] {
  const parts = s.split(":");
  if (parts.length < 2) {
    return [null, null];
  }
  const h = Number(parts[0]);
  const m = Number(parts[1]);
  // Bounds before TZ math: "24:90" would otherwise produce unintended
  // cross-day results when added to the flight_time block.
  if (!Number.isInteger(h) || !Number.isInteger(m) || h < 0 || h > 23 || m < 0 || m > 59) {
    return [null, null];
  }
  return [h, m];
}

function fmtHHMM(h: number, m: number): string {
  return `${pad2(h)}:${pad2(m)}`;
}

function pad2(n: number): string {
  return n.toString().padStart(2, "0");
}
