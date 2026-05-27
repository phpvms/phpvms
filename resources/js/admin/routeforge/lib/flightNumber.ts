/**
 * Flight-number assignment strategies.
 *
 * Four strategies:
 *
 *   1 sequential                      base, base+1, base+2, ... (purely positional)
 *   2 even_odd_by_direction           outbound = base + 2k, return = base + 2k + 1
 *                                      (paired by row.pair_index; outbound = even
 *                                      IFF base is even, which the UI guides toward)
 *   4 same_number_incrementing_legs   every row uses flight_number = base; the
 *                                      assigner sets route_leg = base_leg + index
 *                                      so the strict-duplicate key
 *                                      (airline_id, flight_number, route_code,
 *                                      route_leg) stays unique per row. Tour
 *                                      topology defaults to this strategy because
 *                                      a tour is one flight number across many legs.
 *   5 manual                          caller fills flight_number per row; assigner
 *                                      passes rows through untouched
 *
 * Strategy numbering preserves the original spec's identifiers; "3"
 * (even_outbound_only — outbound and return share the same flight number with
 * no leg disambiguation) was removed because phpvms's `(bundle_id, airline_id,
 * flight_number, route_code, route_leg)` UNIQUE constraint (DB `flights._dup_key`
 * + L4 lint) rejects two rows that share that 5-tuple, and the generator emitted
 * paired returns with `route_code=null`/`route_leg=null` so the keys would
 * collide every time. The strategy was never committable. Strategy 4 is the
 * principled replacement for the "shared flight number" use case.
 *
 * Order assumption for strategy 2: generator emits rows interleaved
 * [out, ret, out, ret, ...] when create_returns is true. row.pair_index is
 * the authoritative grouping key; row.direction is the authoritative
 * outbound/return discriminator. Both come from generator.ts.
 *
 * Strategy 2 is disabled in the UI when create_returns is false
 * (FlightNumberStrategyPicker, task 6.3.10); this module accepts it anyway
 * and treats every row as outbound in that case.
 *
 * Route-leg semantics for strategy 4: `route_leg` is typed `string | null` on
 * the Row but stored as canonical int on the server (`Flight.routeLeg()`
 * accessor coerces). The assigner emits it as a string for client-side
 * symmetry with manual edits in RowTable; canonicalization happens at the
 * server boundary.
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

    case "same_number_incrementing_legs":
      return rows.map((r) => ({
        ...r,
        flight_number: strategy.base,
        route_leg: String(strategy.base_leg + r.index),
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
