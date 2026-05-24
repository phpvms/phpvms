/**
 * Row generator — the sole authority for row production (Decision 2).
 *
 * Pipeline:
 *
 *   1. Pairs       Build (origin, destination, direction, pair_index) tuples
 *                  from the form's topology + create_returns. Filters out
 *                  self-loops per the spec.
 *   2. Geometry    Compute haversine distance + block flight time per pair.
 *                  Cruise speed is min(selected subfleets cruise_speed),
 *                  falling back to config.cruise_speed_kt. Buffer is added
 *                  for climb + descent from config.climb_descent_buffer.
 *   3. Base rows   Materialize Row objects with all non-time fields populated.
 *   4. Times       assignDepartureTimes() sets dpt_time per the time strategy.
 *                  computeArrTime() then derives arr_time + arr_day_shift
 *                  from dpt_time + flight_time using @date-fns/tz.
 *   5. Numbers     assignFlightNumbers() sets flight_number per strategy.
 *
 * Determinism: every step is pure for a given input. The only environmental
 * read is `gen_date`, which the caller supplies (typically `new Date()` in
 * the app's tz). Mulberry32 jitter is seeded so repeat runs over identical
 * inputs produce identical outputs — important for the live-lint UX.
 */

import type {
  AirportSummary,
  Form,
  LegDirection,
  Row,
  RouteForgeServerConfig,
  SubfleetSummary,
} from "../state/types";
import { haversineNm } from "./geo";
import { computeArrTime } from "./timezone";
import { assignDepartureTimes } from "./timeStrategy";
import { assignFlightNumbers } from "./flightNumber";

export type GenerateInput = {
  form: Form;
  airports: Map<string, AirportSummary>;
  subfleets: Map<number, SubfleetSummary>;
  options: {
    /** Reference calendar date for DST-aware TZ math. Typically `new Date()`. */
    gen_date: Date;
    /** From window.routeforgeConfig.config; supplies defaults for missing capability data. */
    server_config: RouteForgeServerConfig;
  };
};

type Pair = {
  origin: string;
  destination: string;
  direction: LegDirection;
  pair_index: number;
};

const DEFAULT_CRUISE_KT = 450;
const DEFAULT_CLIMB_DESCENT_BUFFER_MIN = 20;

/**
 * Main entry. Returns a fully-populated Row[] ready for the preview table.
 * Returns [] when the form is in a state that cannot produce rows (no
 * airline, no origins, no destinations).
 */
export function generate(input: GenerateInput): Row[] {
  const { form, airports } = input;
  if (form.airline_id === null) {
    return [];
  }

  const pairs = buildPairs(form);
  if (pairs.length === 0) {
    return [];
  }

  const cruise = pickCruiseSpeed(input);
  const climbBuffer = (input.options.server_config.climb_descent_buffer ??
    DEFAULT_CLIMB_DESCENT_BUFFER_MIN) as number;

  // Step 3: base rows with geometry + tz metadata, but no times yet.
  const baseRows: Row[] = pairs.map((p, index) => {
    const distance = computeDistance(p, airports);
    const flightTime = computeFlightTime(distance, cruise, climbBuffer);
    const dptAirport = airports.get(p.origin);
    const arrAirport = airports.get(p.destination);

    return {
      index,
      pair_index: p.pair_index,
      direction: p.direction,
      airline_id: form.airline_id as number,
      // Flight number placeholder — assignFlightNumbers replaces.
      flight_number: 0,
      route_code: null,
      route_leg: null,
      dpt_airport_id: p.origin,
      arr_airport_id: p.destination,
      dpt_timezone: dptAirport?.timezone ?? null,
      arr_timezone: arrAirport?.timezone ?? null,
      // dpt_time placeholder — assignDepartureTimes fills.
      dpt_time: "00:00",
      // arr_time placeholder — computeArrTime fills via the map below.
      arr_time: "00:00",
      arr_day_shift: 0,
      distance_nm: distance,
      flight_time: flightTime,
      days_mask: form.days_mask,
      flight_type: form.flight_type,
      enabled: true,
      edited: false,
    };
  });

  // Step 4a: departure times (per-origin slot logic + jitter).
  const withDpt = assignDepartureTimes(baseRows, form.time_strategy);

  // Step 4b: derived arrival times using the freshly-assigned dpt_time.
  const withArr = withDpt.map((r) => {
    const arr = computeArrTime(
      r.dpt_time,
      r.dpt_timezone,
      r.arr_timezone,
      r.flight_time,
      input.options.gen_date,
    );
    return {
      ...r,
      arr_time: arr.arr_local,
      arr_day_shift: arr.day_shift,
    };
  });

  // Step 5: flight numbers (strategy-aware, uses pair_index/direction).
  return assignFlightNumbers(withArr, form.flight_number_strategy);
}

// ──────────────────────────────────────────────────────────────────────────
// Pair construction
// ──────────────────────────────────────────────────────────────────────────

/**
 * Build the leg list per the form's mode + create_returns.
 *
 * cartesian:  every origin × every destination (excluding self-loops).
 *             If create_returns: each (O,D) pair emits [outbound, return]
 *             interleaved so flight-number strategy 2 produces the
 *             documented even/odd parity (spec scenario).
 * chain:      sequential pairs (origins[0]→origins[1], [1]→[2], ...).
 *             Destinations list is ignored. create_returns has no v1
 *             semantic for chain (spec doesn't define it); ignored.
 */
function buildPairs(form: Form): Pair[] {
  if (form.mode === "chain") {
    return buildChainPairs(form.origins);
  }
  return buildCartesianPairs(form.origins, form.destinations, form.create_returns);
}

function buildCartesianPairs(
  origins: string[],
  destinations: string[],
  createReturns: boolean,
): Pair[] {
  const pairs: Pair[] = [];
  let pairIndex = 0;
  for (const origin of origins) {
    for (const destination of destinations) {
      if (origin === destination) {
        // L6 (origin = destination): generator excludes per spec.
        continue;
      }
      pairs.push({ origin, destination, direction: "outbound", pair_index: pairIndex });
      if (createReturns) {
        pairs.push({
          origin: destination,
          destination: origin,
          direction: "return",
          pair_index: pairIndex,
        });
      }
      pairIndex++;
    }
  }
  return pairs;
}

function buildChainPairs(origins: string[]): Pair[] {
  const pairs: Pair[] = [];
  // Contiguous pair_index across emitted pairs: using the loop index `i`
  // leaves gaps when adjacent duplicates are skipped, which would shift
  // flight-number strategy math (even_odd_by_direction / even_outbound_only)
  // for subsequent rows.
  let pairIndex = 0;
  for (let i = 0; i < origins.length - 1; i++) {
    const origin = origins[i] as string;
    const destination = origins[i + 1] as string;
    if (origin === destination) {
      continue;
    }
    pairs.push({ origin, destination, direction: "outbound", pair_index: pairIndex });
    pairIndex++;
  }
  return pairs;
}

// ──────────────────────────────────────────────────────────────────────────
// Geometry
// ──────────────────────────────────────────────────────────────────────────

function computeDistance(p: Pair, airports: Map<string, AirportSummary>): number {
  const o = airports.get(p.origin);
  const d = airports.get(p.destination);
  if (
    o === undefined ||
    d === undefined ||
    o.lat === null ||
    o.lon === null ||
    d.lat === null ||
    d.lon === null
  ) {
    return 0;
  }
  return Math.round(haversineNm(o.lat, o.lon, d.lat, d.lon) * 10) / 10;
}

function computeFlightTime(
  distanceNm: number,
  cruiseKt: number,
  climbDescentBuffer: number,
): number {
  if (distanceNm <= 0 || cruiseKt <= 0) {
    return Math.max(0, climbDescentBuffer);
  }
  // hours × 60 = minutes, rounded to nearest minute, plus climb/descent buffer.
  const cruiseMinutes = Math.round((distanceNm / cruiseKt) * 60);
  return cruiseMinutes + climbDescentBuffer;
}

/**
 * Cruise-speed pick: minimum cruise_speed across selected subfleets
 * (conservative = longest flight_time = most generous schedule slack),
 * falling back to config.cruise_speed_kt or the v1 default of 450 kt.
 *
 * Rationale: the alternative ("max cruise" or "first non-null") would
 * underestimate block time when a slower aircraft in the selection ends
 * up flying the route. Conservative wins for schedule realism.
 */
function pickCruiseSpeed(input: GenerateInput): number {
  const fromConfig = (input.options.server_config.cruise_speed_kt ?? DEFAULT_CRUISE_KT) as number;
  const selected = input.form.subfleet_ids
    .map((id) => input.subfleets.get(id))
    .filter((s): s is SubfleetSummary => s !== undefined);
  const speeds = selected
    .map((s) => s.cruise_speed)
    .filter((v): v is number => v !== null && v > 0);
  if (speeds.length === 0) {
    return fromConfig;
  }
  return Math.min(...speeds);
}
