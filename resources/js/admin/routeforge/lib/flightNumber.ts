/**
 * Flight-number assignment strategies.
 *
 * Four strategies per spec (route-forge-tool/spec.md "Flight-number strategy"):
 *
 *   1 sequential              base, base+1, base+2, ... (purely positional)
 *   2 even_odd_by_direction   outbound = base + 2k, return = base + 2k + 1
 *                              (paired by row.pair_index; outbound = even
 *                              IFF base is even, which the UI guides toward)
 *   3 even_outbound_only      outbound = base + 2k, return SHARES outbound's
 *                              number (operational shorthand: same physical
 *                              tail flies both directions of a turn)
 *   5 manual                  caller fills flight_number per row; assigner
 *                              passes rows through untouched
 *
 * Order assumption for strategies 2 + 3: generator emits rows interleaved
 * [out, ret, out, ret, ...] when create_returns is true. row.pair_index is
 * the authoritative grouping key; row.direction is the authoritative
 * outbound/return discriminator. Both come from generator.ts.
 *
 * Strategy 2 is disabled in the UI when create_returns is false
 * (FlightNumberStrategyPicker, task 6.3.10); this module accepts it anyway
 * and treats every row as outbound in that case.
 */

import type { FlightNumberStrategy, Row } from "../state/types";

/**
 * Returns a new row list with `flight_number` assigned per the strategy.
 * Input rows are not mutated.
 */
export function assignFlightNumbers(rows: Row[], strategy: FlightNumberStrategy): Row[] {
  switch (strategy.kind) {
    case "sequential":
      return rows.map((r, i) => ({ ...r, flight_number: strategy.base + i }));

    case "even_odd_by_direction":
      return rows.map((r) => {
        const offset = r.direction === "outbound" ? 0 : 1;
        return { ...r, flight_number: strategy.base + r.pair_index * 2 + offset };
      });

    case "even_outbound_only":
      return rows.map((r) => ({
        ...r,
        flight_number: strategy.base + r.pair_index * 2,
      }));

    case "manual":
      // User-provided per row; preserve whatever the caller set.
      return rows.map((r) => ({ ...r }));

    default: {
      // Defensive fallback: a stale or corrupt persisted draft could carry
      // an unknown `kind`. TypeScript's exhaustive check would catch this
      // at compile time, but the runtime guard prevents `undefined` rows
      // from reaching downstream consumers.
      // eslint-disable-next-line no-console
      console.warn("assignFlightNumbers: unknown strategy.kind", strategy);
      return rows.map((r) => ({ ...r }));
    }
  }
}
