/**
 * Editable row table.
 *
 * Columns: select checkbox, row #, dep ICAO, arr ICAO, flight #, dpt local,
 * arr local, distance, days mask, lint icon.
 *
 * Edit surface (v1):
 *   - `enabled`         — checkbox column
 *   - `flight_number`   — inline number input
 *   - `dpt_time`        — inline time input; editing recomputes `arr_time`
 *                         and `arr_day_shift` via lib/timezone.computeArrTime
 *                         so the user sees the consequence immediately
 *   - All other cells read-only
 *
 * Days mask, airports, distance, flight_time are not editable in v1 —
 * defer per-cell day-mask editing + arrival-time override to v2 (would
 * need a row drawer with the days/timezone sub-controls).
 *
 * Every edit stamps `edited: true` on the row. The dirty tracker
 * (lib/lifecycle.ts) reads that flag to decide whether to surface the
 * regenerate-confirmation modal.
 *
 * No virtualization v1: row cap is 100 (lint L10), DOM handles it.
 */

import { computeArrTime } from "../lib/timezone";
import { rows } from "../state/store";
import type { Row } from "../state/types";
import { RowLintIcon } from "./RowLintIcon";

const TH =
  "sticky top-0 z-10 bg-gray-50 px-2 py-1.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300";
const TD = "px-2 py-1 text-sm text-gray-700 dark:text-gray-200";
const INPUT_INLINE =
  "w-full rounded border border-transparent bg-transparent px-1 py-0.5 text-sm focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-0 dark:focus:bg-gray-900";

export function RowTable() {
  const list = rows.value;

  if (list.length === 0) {
    return (
      <div class="rounded border border-dashed border-gray-300 bg-gray-50 px-6 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
        No rows yet. Click <span class="font-medium">Generate</span> to materialize from the form
        above.
      </div>
    );
  }

  function updateRow(index: number, patch: Partial<Row>): void {
    const current = list[index];
    if (current === undefined) {
      return;
    }
    const updated: Row = { ...current, ...patch, edited: true };
    const next = list.slice();
    next[index] = updated;
    rows.value = next;
  }

  function setEnabled(index: number, enabled: boolean): void {
    updateRow(index, { enabled });
  }

  function setFlightNumber(index: number, raw: string): void {
    const n = Number.parseInt(raw, 10);
    if (Number.isNaN(n)) {
      return;
    }
    updateRow(index, { flight_number: n });
  }

  function setDptTime(index: number, dpt: string): void {
    const current = list[index];
    if (current === undefined) {
      return;
    }
    const arr = computeArrTime(
      dpt,
      current.dpt_timezone,
      current.arr_timezone,
      current.flight_time,
      new Date(),
    );
    updateRow(index, {
      dpt_time: dpt,
      arr_time: arr.arr_local,
      arr_day_shift: arr.day_shift,
    });
  }

  function toggleAll(checked: boolean): void {
    rows.value = list.map((r) => ({ ...r, enabled: checked }));
  }

  const allChecked = list.every((r) => r.enabled);
  const someChecked = list.some((r) => r.enabled);

  return (
    <div class="max-h-[60vh] overflow-auto rounded border border-gray-200 dark:border-gray-700">
      <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead>
          <tr>
            <th class={TH}>
              <input
                type="checkbox"
                aria-label="Select all rows"
                checked={allChecked}
                indeterminate={someChecked && !allChecked}
                onChange={(e) => toggleAll((e.currentTarget as HTMLInputElement).checked)}
              />
            </th>
            <th class={TH}>#</th>
            <th class={TH}>From</th>
            <th class={TH}>To</th>
            <th class={TH}>Flight #</th>
            <th class={TH}>Dpt (local)</th>
            <th class={TH}>Arr (local)</th>
            <th class={TH}>Distance</th>
            <th class={TH}>Days</th>
            <th class={TH}>Lint</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          {list.map((r) => (
            <RowTr
              key={r.index}
              row={r}
              onToggleEnabled={(checked) => setEnabled(r.index, checked)}
              onFlightNumber={(raw) => setFlightNumber(r.index, raw)}
              onDptTime={(dpt) => setDptTime(r.index, dpt)}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

type RowTrProps = {
  row: Row;
  onToggleEnabled: (checked: boolean) => void;
  onFlightNumber: (raw: string) => void;
  onDptTime: (dpt: string) => void;
};

function RowTr({ row, onToggleEnabled, onFlightNumber, onDptTime }: RowTrProps) {
  const dayLabel = formatDaysMask(row.days_mask);
  const arrLabel =
    row.arr_day_shift === 0 ? row.arr_time : `${row.arr_time} +${row.arr_day_shift}d`;
  const directionBadge =
    row.direction === "return" ? (
      <span class="ml-1 rounded bg-gray-200 px-1 text-[10px] text-gray-700 dark:bg-gray-700 dark:text-gray-300">
        RET
      </span>
    ) : null;

  return (
    <tr class={row.edited ? "bg-yellow-50/40 dark:bg-yellow-900/10" : undefined}>
      <td class={TD}>
        <input
          type="checkbox"
          aria-label={`Enable row ${row.index + 1}`}
          checked={row.enabled}
          onChange={(e) => onToggleEnabled((e.currentTarget as HTMLInputElement).checked)}
        />
      </td>
      <td class={`${TD} text-gray-500 dark:text-gray-400`}>{row.index + 1}</td>
      <td class={`${TD} font-mono`}>
        {row.dpt_airport_id}
        {directionBadge}
      </td>
      <td class={`${TD} font-mono`}>{row.arr_airport_id}</td>
      <td class={TD}>
        <input
          type="number"
          min={1}
          step={1}
          class={INPUT_INLINE}
          value={row.flight_number}
          onInput={(e) => onFlightNumber((e.currentTarget as HTMLInputElement).value)}
        />
      </td>
      <td class={TD}>
        <input
          type="time"
          class={INPUT_INLINE}
          value={row.dpt_time}
          onInput={(e) => onDptTime((e.currentTarget as HTMLInputElement).value)}
        />
      </td>
      <td class={`${TD} font-mono text-xs text-gray-500 dark:text-gray-400`}>{arrLabel}</td>
      <td class={`${TD} text-xs`}>{row.distance_nm.toFixed(1)} nm</td>
      <td class={`${TD} font-mono text-xs`}>{dayLabel}</td>
      <td class={TD}>
        <RowLintIcon rowIndex={row.index} />
      </td>
    </tr>
  );
}

const DAY_LETTERS = ["M", "T", "W", "T", "F", "S", "S"];

function formatDaysMask(mask: number): string {
  return DAY_LETTERS.map((letter, i) => ((mask & (1 << i)) !== 0 ? letter : "·")).join("");
}
