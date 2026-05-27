/**
 * Flight-number strategy picker.
 *
 * Four strategies:
 *   1. sequential                     base, base+1, base+2, ...
 *   2. even_odd_by_direction          outbound = even, return = odd-of-paired-outbound
 *   4. same_number_incrementing_legs  all rows share `base`; `route_leg` walks
 *                                      from `base_leg` (1 by default). Tour topology
 *                                      defaults to this strategy.
 *   5. manual                         user types each number per row
 *
 * Strategy 3 (`even_outbound_only` — outbound and return share the same
 * flight number with NO leg disambiguation) was removed: phpvms's
 * `(bundle_id, airline_id, flight_number, route_code, route_leg)` UNIQUE
 * key (DB + L4 lint) rejected the resulting duplicate 5-tuples, so the
 * strategy was never committable. Strategy 4 is the principled replacement
 * — it solves the same "one flight number, many legs" use case by making
 * `route_leg` the disambiguator. See `lib/flightNumber.ts` for full rationale.
 *
 * Strategy 2 is disabled when `create_returns` is false (proposal: "Strategy
 * 2 disabled when 'Create returns' off"). The select gracefully recovers if
 * the user lands on a stale strategy 2 selection after toggling returns off —
 * we don't auto-rewrite the form here; the disabled <option> is a visible
 * signal and the user re-picks.
 *
 * Base number lives on every strategy except `manual`; the base input is
 * rendered conditionally below the strategy select. Strategy 4 adds a
 * second input ("Base leg number") below it.
 */

import { form } from "../state/store";
import type { FlightNumberStrategy } from "../state/types";
import { Field, INPUT_CLASS } from "./Field";

type StrategyKind = FlightNumberStrategy["kind"];

const STRATEGY_LABELS: Record<StrategyKind, string> = {
  sequential: "Sequential (base, base+1, base+2, …)",
  even_odd_by_direction: "Even/odd by direction (outbound even, return odd)",
  same_number_incrementing_legs: "Same flight number, incrementing legs",
  manual: "Manual (enter each row individually)",
};

const STRATEGY_ORDER: StrategyKind[] = [
  "sequential",
  "even_odd_by_direction",
  "same_number_incrementing_legs",
  "manual",
];

const DEFAULT_BASE_LEG = 1;

function defaultForKind(kind: StrategyKind, currentBase: number): FlightNumberStrategy {
  if (kind === "manual") {
    return { kind: "manual" };
  }
  if (kind === "same_number_incrementing_legs") {
    return { kind, base: currentBase, base_leg: DEFAULT_BASE_LEG };
  }
  return { kind, base: currentBase };
}

export function FlightNumberStrategyPicker() {
  const f = form.value;
  const strat = f.flight_number_strategy;
  const returnsOff = !f.create_returns;
  const currentBase = strat.kind === "manual" ? 100 : strat.base;

  function handleKindChange(e: Event): void {
    const next = (e.currentTarget as HTMLSelectElement).value as StrategyKind;
    form.value = {
      ...f,
      flight_number_strategy: defaultForKind(next, currentBase),
    };
  }

  function handleBaseChange(e: Event): void {
    if (strat.kind === "manual") {
      return;
    }
    const next = Number.parseInt((e.currentTarget as HTMLInputElement).value, 10);
    if (Number.isNaN(next)) {
      return;
    }
    form.value = {
      ...f,
      flight_number_strategy: { ...strat, base: next },
    };
  }

  function handleBaseLegChange(e: Event): void {
    if (strat.kind !== "same_number_incrementing_legs") {
      return;
    }
    const next = Number.parseInt((e.currentTarget as HTMLInputElement).value, 10);
    if (Number.isNaN(next)) {
      return;
    }
    form.value = {
      ...f,
      // Clamp to >= 1 — route_leg=0 collapses to NULL server-side via
      // canonicalizeRoutePart(), which would erase leg disambiguation
      // mid-batch and recreate the exact L4 5-tuple collision the
      // strategy exists to avoid.
      flight_number_strategy: { ...strat, base_leg: Math.max(1, next) },
    };
  }

  return (
    <>
      <Field
        label="Flight number strategy"
        htmlFor="rf-fn-strategy"
        hint={
          returnsOff
            ? 'Enable "Create return flights" to use the even/odd-by-direction strategy.'
            : "Choose how flight numbers are assigned across generated rows."
        }
      >
        <select
          id="rf-fn-strategy"
          class={INPUT_CLASS}
          value={strat.kind}
          onChange={handleKindChange}
        >
          {STRATEGY_ORDER.map((k) => (
            <option key={k} value={k} disabled={k === "even_odd_by_direction" && returnsOff}>
              {STRATEGY_LABELS[k]}
            </option>
          ))}
        </select>
      </Field>
      {strat.kind !== "manual" && (
        <Field
          label="Base flight number"
          htmlFor="rf-fn-base"
          hint="Starting number for the first generated row."
        >
          <input
            id="rf-fn-base"
            type="number"
            min={1}
            step={1}
            class={INPUT_CLASS}
            value={strat.base}
            onInput={handleBaseChange}
          />
        </Field>
      )}
      {strat.kind === "same_number_incrementing_legs" && (
        <Field
          label="Base leg number"
          htmlFor="rf-fn-base-leg"
          hint="First row gets this leg; subsequent rows increment by one."
        >
          <input
            id="rf-fn-base-leg"
            type="number"
            min={1}
            step={1}
            class={INPUT_CLASS}
            value={strat.base_leg}
            onInput={handleBaseLegChange}
          />
        </Field>
      )}
    </>
  );
}
