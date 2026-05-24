/**
 * Time strategy controls — picks one of the four strategies + their config.
 *
 * Strategy kinds (tagged union, see state/types.ts):
 *   fixed   base_time + jitter
 *   spread  base_time + interval_minutes + jitter
 *   banked  base_time + bank_count + bank_spacing_minutes + jitter
 *   redeye  base_time + window_minutes + jitter
 *
 * UI shape: a "segmented control" (radio-style buttons) for the strategy
 * kind + the kind-specific sub-fields + the jitter section (common to all 4).
 *
 * When the user switches kinds, we synthesize a fresh strategy object with
 * sensible defaults for the new kind, carrying over `base_time` and the
 * `jitter` config so kind-switching feels continuous rather than a reset.
 */

import { form } from "../state/store";
import type { JitterConfig, TimeStrategy } from "../state/types";
import { Field, INPUT_CLASS } from "./Field";

type StrategyKind = TimeStrategy["kind"];

const KIND_LABELS: Record<StrategyKind, string> = {
  fixed: "Fixed",
  spread: "Spread",
  banked: "Banked",
  redeye: "Red-eye",
};

const KIND_ORDER: StrategyKind[] = ["fixed", "spread", "banked", "redeye"];

function withKind(current: TimeStrategy, next: StrategyKind): TimeStrategy {
  const base_time = current.base_time;
  const jitter = current.jitter;
  switch (next) {
    case "fixed":
      return { kind: "fixed", base_time, jitter };
    case "spread":
      return { kind: "spread", base_time, interval_minutes: 60, jitter };
    case "banked":
      return { kind: "banked", base_time, bank_count: 3, bank_spacing_minutes: 120, jitter };
    case "redeye":
      return { kind: "redeye", base_time, window_minutes: 240, jitter };
  }
}

export function TimeStrategyControls() {
  const f = form.value;
  const ts = f.time_strategy;

  function setStrategy(next: TimeStrategy): void {
    form.value = { ...f, time_strategy: next };
  }

  function changeKind(next: StrategyKind): void {
    setStrategy(withKind(ts, next));
  }

  function changeBaseTime(e: Event): void {
    const value = (e.currentTarget as HTMLInputElement).value;
    setStrategy({ ...ts, base_time: value });
  }

  function changeJitter(patch: Partial<JitterConfig>): void {
    setStrategy({ ...ts, jitter: { ...ts.jitter, ...patch } });
  }

  return (
    <div class="mb-3">
      <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
        Time strategy
      </span>

      {/* Segmented control */}
      <div
        class="mb-3 inline-flex rounded border border-gray-300 dark:border-gray-600"
        role="group"
      >
        {KIND_ORDER.map((k, i) => {
          const active = ts.kind === k;
          return (
            <button
              key={k}
              type="button"
              aria-pressed={active}
              class={
                "px-3 py-1.5 text-sm " +
                (i === 0 ? "rounded-l " : "") +
                (i === KIND_ORDER.length - 1 ? "rounded-r " : "") +
                (i > 0 ? "border-l border-gray-300 dark:border-gray-600 " : "") +
                (active
                  ? "bg-primary-600 text-white"
                  : "bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700")
              }
              onClick={() => changeKind(k)}
            >
              {KIND_LABELS[k]}
            </button>
          );
        })}
      </div>

      {/* Base time — every strategy has one */}
      <Field label="Base departure time" htmlFor="rf-time-base" hint="HH:MM in origin local time.">
        <input
          id="rf-time-base"
          type="time"
          class={INPUT_CLASS}
          value={ts.base_time}
          onInput={changeBaseTime}
        />
      </Field>

      {/* Kind-specific sub-fields */}
      {ts.kind === "spread" && (
        <Field
          label="Interval (minutes)"
          htmlFor="rf-time-interval"
          hint="Gap between successive departures, per origin."
        >
          <input
            id="rf-time-interval"
            type="number"
            min={1}
            step={1}
            class={INPUT_CLASS}
            value={ts.interval_minutes}
            onInput={(e) =>
              setStrategy({
                ...ts,
                interval_minutes: Math.max(
                  1,
                  Number.parseInt((e.currentTarget as HTMLInputElement).value, 10) || 1,
                ),
              })
            }
          />
        </Field>
      )}

      {ts.kind === "banked" && (
        <>
          <Field
            label="Bank count"
            htmlFor="rf-time-bank-count"
            hint="Number of departure banks per origin per day."
          >
            <input
              id="rf-time-bank-count"
              type="number"
              min={1}
              step={1}
              class={INPUT_CLASS}
              value={ts.bank_count}
              onInput={(e) =>
                setStrategy({
                  ...ts,
                  bank_count: Math.max(
                    1,
                    Number.parseInt((e.currentTarget as HTMLInputElement).value, 10) || 1,
                  ),
                })
              }
            />
          </Field>
          <Field
            label="Bank spacing (minutes)"
            htmlFor="rf-time-bank-spacing"
            hint="Gap between successive banks."
          >
            <input
              id="rf-time-bank-spacing"
              type="number"
              min={1}
              step={1}
              class={INPUT_CLASS}
              value={ts.bank_spacing_minutes}
              onInput={(e) =>
                setStrategy({
                  ...ts,
                  bank_spacing_minutes: Math.max(
                    1,
                    Number.parseInt((e.currentTarget as HTMLInputElement).value, 10) || 1,
                  ),
                })
              }
            />
          </Field>
        </>
      )}

      {ts.kind === "redeye" && (
        <Field
          label="Window (minutes)"
          htmlFor="rf-time-window"
          hint="Total late-night window across which rows distribute."
        >
          <input
            id="rf-time-window"
            type="number"
            min={1}
            step={1}
            class={INPUT_CLASS}
            value={ts.window_minutes}
            onInput={(e) =>
              setStrategy({
                ...ts,
                window_minutes: Math.max(
                  1,
                  Number.parseInt((e.currentTarget as HTMLInputElement).value, 10) || 1,
                ),
              })
            }
          />
        </Field>
      )}

      {/* Jitter — common across all strategies */}
      <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
        <label class="mb-2 inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
          <input
            type="checkbox"
            class="h-4 w-4"
            checked={ts.jitter.enabled}
            onChange={(e) =>
              changeJitter({ enabled: (e.currentTarget as HTMLInputElement).checked })
            }
          />
          Jitter
        </label>
        {ts.jitter.enabled && (
          <div class="grid grid-cols-2 gap-2">
            <Field label="±Minutes" htmlFor="rf-jitter-minutes" hint="Max random offset per row.">
              <input
                id="rf-jitter-minutes"
                type="number"
                min={0}
                step={1}
                class={INPUT_CLASS}
                value={ts.jitter.minutes}
                onInput={(e) =>
                  changeJitter({
                    minutes: Math.max(
                      0,
                      Number.parseInt((e.currentTarget as HTMLInputElement).value, 10) || 0,
                    ),
                  })
                }
              />
            </Field>
            <Field label="Seed" htmlFor="rf-jitter-seed" hint="Deterministic across regenerations.">
              <input
                id="rf-jitter-seed"
                type="number"
                step={1}
                class={INPUT_CLASS}
                value={ts.jitter.seed}
                onInput={(e) =>
                  changeJitter({
                    seed: Number.parseInt((e.currentTarget as HTMLInputElement).value, 10) || 1,
                  })
                }
              />
            </Field>
          </div>
        )}
      </div>
    </div>
  );
}
