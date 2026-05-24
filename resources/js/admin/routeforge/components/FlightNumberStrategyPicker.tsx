/**
 * Flight-number strategy picker.
 *
 * Four strategies (per spec):
 *   1. sequential               base, base+1, base+2, ...
 *   2. even_odd_by_direction    outbound = even, return = odd-of-paired-outbound
 *   3. even_outbound_only       outbound = even, return SHARES outbound's number
 *   5. manual                   user types each number per row
 *
 * Strategy 2 is disabled when `create_returns` is false (proposal: "Strategy
 * 2 disabled when 'Create returns' off"). The select gracefully recovers if
 * the user lands on a stale strategy 2 selection after toggling returns off —
 * we don't auto-rewrite the form here; the disabled <option> is a visible
 * signal and the user re-picks.
 *
 * Base number lives on every strategy except `manual`; the base input is
 * rendered conditionally below the strategy select.
 */

import { form } from "../state/store";
import type { FlightNumberStrategy } from "../state/types";
import { Field, INPUT_CLASS } from "./Field";

type StrategyKind = FlightNumberStrategy["kind"];

const STRATEGY_LABELS: Record<StrategyKind, string> = {
  sequential: "Sequential (base, base+1, base+2, …)",
  even_odd_by_direction: "Even/odd by direction (outbound even, return odd)",
  even_outbound_only: "Even outbound only (returns share outbound number)",
  manual: "Manual (enter each row individually)",
};

const STRATEGY_ORDER: StrategyKind[] = [
  "sequential",
  "even_odd_by_direction",
  "even_outbound_only",
  "manual",
];

function defaultForKind(kind: StrategyKind, currentBase: number): FlightNumberStrategy {
  if (kind === "manual") {
    return { kind: "manual" };
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
    </>
  );
}
