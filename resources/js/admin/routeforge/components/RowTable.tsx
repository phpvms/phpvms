/**
 * Editable row list (two-line grid layout, click-to-edit).
 *
 * Each logical row renders as a single grid row with four columns. Column 3
 * holds two visual lines:
 *
 *   Line 1: ⟨FL ####⟩ [/L.⟨leg⟩]  ICAO → ICAO  [RET]
 *   Line 2: Dpt ⟨HH:MM⟩  Arr HH:MM +Nd  ·  ####nm  ·  MTWTFSS
 *
 * Editable fields (click-to-edit, one cell active at a time):
 *   - `enabled`        — checkbox column (always interactive; no swap)
 *   - `flight_number`  — text → number input on click; Enter / blur commits;
 *                         Esc cancels
 *   - `route_leg`      — rendered as `/L.<n>` after the flight number when
 *                         the row has a leg set (the
 *                         `same_number_incrementing_legs` strategy populates
 *                         it; other strategies leave it null). Click to edit
 *                         as a number input; blank / 0 / negative clears it
 *                         back to null (matches the server's
 *                         `canonicalizeRoutePart()` rules in Flight.php).
 *                         Adding a leg to a row that doesn't have one is
 *                         done by selecting the legs strategy, not by row
 *                         edit — keeps the row UI uncluttered when most
 *                         flights don't use legs.
 *   - `departure_time` — text → time input on click. Editing recomputes
 *                         `arrival_time` + `arr_day_shift` via
 *                         lib/timezone.computeArrTime so the user sees the
 *                         consequence immediately (block time + DST shift
 *                         applied against the row's IANA timezones).
 *
 * All other cells (airports, distance, days, arrival, flight_time) are
 * read-only in this surface; per-row drawer for days_mask + arr override is
 * deferred (see git history for the previous v1 table comment).
 *
 * Every edit stamps `edited: true` on the row. The dirty tracker
 * (lib/lifecycle.ts) reads that flag to surface the regenerate-confirmation
 * modal when the form changes after manual edits.
 *
 * No virtualization: row cap is 100 (lint L10), DOM handles it.
 */

import { useEffect, useRef, useState } from "preact/hooks";

import { computeArrTime } from "../lib/timezone";
import { rows } from "../state/store";
import type { Row } from "../state/types";
import { RowLintIcon } from "./RowLintIcon";

type EditableField = "flight_number" | "route_leg" | "departure_time";
type EditingCell = { rowIndex: number; field: EditableField } | null;

const DAY_LETTERS = ["M", "T", "W", "T", "F", "S", "S"];

// 4-column grid: checkbox · # · two-line content · lint icon.
const GRID_COLS = "grid grid-cols-[auto_2.5rem_1fr_auto] items-start gap-x-3";

const HEADER_CLS = `${GRID_COLS} sticky top-0 z-10 border-b border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300`;
const ROW_CLS = `${GRID_COLS} border-b border-gray-100 px-3 py-1.5 text-sm last:border-b-0 dark:border-gray-800`;

const CLICKABLE_CELL =
  "rounded px-1 text-left hover:bg-gray-100 focus:bg-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:hover:bg-gray-800 dark:focus:bg-gray-800";

export function RowTable() {
  const list = rows.value;
  const [editing, setEditing] = useState<EditingCell>(null);

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
    const next = list.slice();
    next[index] = { ...current, ...patch, edited: true };
    rows.value = next;
  }

  function setEnabled(index: number, enabled: boolean): void {
    updateRow(index, { enabled });
  }

  function commitFlightNumber(index: number, raw: string): void {
    const n = Number.parseInt(raw, 10);
    if (!Number.isNaN(n)) {
      // Clamp to >= 1: typed input allows 0 and negatives even with min={1}
      // on the input element. State stays valid regardless of how the value
      // got in.
      updateRow(index, { flight_number: Math.max(1, n) });
    }
    setEditing(null);
  }

  function commitRouteLeg(index: number, raw: string): void {
    // Empty / 0 / negative all canonicalize to null on the server
    // (Flight.canonicalizeRoutePart). Mirror that here so the displayed
    // state matches what would persist on commit.
    const trimmed = raw.trim();
    if (trimmed === "") {
      updateRow(index, { route_leg: null });
      setEditing(null);
      return;
    }
    const n = Number.parseInt(trimmed, 10);
    if (Number.isNaN(n) || n <= 0) {
      updateRow(index, { route_leg: null });
    } else {
      updateRow(index, { route_leg: String(n) });
    }
    setEditing(null);
  }

  function commitDepartureTime(index: number, time: string): void {
    if (time !== "") {
      const current = list[index];
      if (current !== undefined) {
        // Recompute arrival_time + arr_day_shift from the new departure_time
        // using the row's IANA timezones. Matches the generator's step 4b
        // derivation (lib/generator.ts) so an edit produces the same row
        // shape as a regeneration would for the new departure.
        const arr = computeArrTime(
          time,
          current.dpt_timezone,
          current.arr_timezone,
          current.flight_time,
          new Date(),
        );
        updateRow(index, {
          departure_time: time,
          arrival_time: arr.arr_local,
          arr_day_shift: arr.day_shift,
        });
      }
    }
    setEditing(null);
  }

  function toggleAll(checked: boolean): void {
    // Stamp `edited: true` so the dirty tracker treats bulk toggles like any
    // other row edit — regenerate-confirmation should still fire if the user
    // bulk-toggles and then changes the form.
    rows.value = list.map((r) =>
      r.enabled === checked ? r : { ...r, enabled: checked, edited: true },
    );
  }

  const allChecked = list.every((r) => r.enabled);
  const someChecked = list.some((r) => r.enabled);

  return (
    <div
      role="grid"
      aria-rowcount={list.length + 1}
      class="max-h-[60vh] overflow-auto rounded border border-gray-200 dark:border-gray-700"
    >
      {/* Header */}
      <div role="row" aria-rowindex={1} class={HEADER_CLS}>
        <div role="columnheader">
          <input
            type="checkbox"
            aria-label="Select all rows"
            checked={allChecked}
            indeterminate={someChecked && !allChecked}
            onChange={(e) => toggleAll((e.currentTarget as HTMLInputElement).checked)}
          />
        </div>
        <div role="columnheader">#</div>
        <div role="columnheader">Flight / Route / Schedule</div>
        <div role="columnheader" class="text-right">
          Lint
        </div>
      </div>

      {/* Rows */}
      {list.map((r) => (
        <RowItem
          key={r.index}
          row={r}
          editing={editing !== null && editing.rowIndex === r.index ? editing.field : null}
          onStartEdit={(field) => setEditing({ rowIndex: r.index, field })}
          onCancelEdit={() => setEditing(null)}
          onToggleEnabled={(checked) => setEnabled(r.index, checked)}
          onCommitFlightNumber={(raw) => commitFlightNumber(r.index, raw)}
          onCommitRouteLeg={(raw) => commitRouteLeg(r.index, raw)}
          onCommitDepartureTime={(time) => commitDepartureTime(r.index, time)}
        />
      ))}
    </div>
  );
}

type RowItemProps = {
  row: Row;
  editing: EditableField | null;
  onStartEdit: (field: EditableField) => void;
  onCancelEdit: () => void;
  onToggleEnabled: (checked: boolean) => void;
  onCommitFlightNumber: (raw: string) => void;
  onCommitRouteLeg: (raw: string) => void;
  onCommitDepartureTime: (time: string) => void;
};

function RowItem({
  row,
  editing,
  onStartEdit,
  onCancelEdit,
  onToggleEnabled,
  onCommitFlightNumber,
  onCommitRouteLeg,
  onCommitDepartureTime,
}: RowItemProps) {
  const arrLabel =
    row.arr_day_shift === 0 ? row.arrival_time : `${row.arrival_time} +${row.arr_day_shift}d`;
  const dayLabel = formatDaysMask(row.days_mask);
  const directionBadge =
    row.direction === "return" ? (
      <span class="ml-1 rounded bg-gray-200 px-1 text-[10px] font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
        RET
      </span>
    ) : null;

  return (
    <div
      role="row"
      aria-rowindex={row.index + 2}
      class={`${ROW_CLS} ${row.edited ? "bg-yellow-50/40 dark:bg-yellow-900/10" : ""}`}
    >
      {/* Col 1: checkbox */}
      <div role="gridcell" class="pt-0.5">
        <input
          type="checkbox"
          aria-label={`Enable row ${row.index + 1}`}
          checked={row.enabled}
          onChange={(e) => onToggleEnabled((e.currentTarget as HTMLInputElement).checked)}
        />
      </div>

      {/* Col 2: row number */}
      <div role="gridcell" class="pt-0.5 text-xs text-gray-500 dark:text-gray-400">
        {row.index + 1}
      </div>

      {/* Col 3: two-line content */}
      <div role="gridcell" class="flex min-w-0 flex-col gap-0.5">
        {/* Line 1: flight number (+ optional leg) + route */}
        <div class="flex flex-wrap items-baseline gap-x-3">
          {/* Flight number + leg form one composite identifier, so they
              share a tighter visual group (gap-x-0.5) — matches the
              `<num>/L.<leg>` rendering in Flight::flight_id. Routing
              metadata after the group keeps the original spacing. */}
          <span class="inline-flex items-baseline gap-x-0.5">
            {editing === "flight_number" ? (
              <FlightNumberEditor
                initial={row.flight_number}
                onCommit={onCommitFlightNumber}
                onCancel={onCancelEdit}
              />
            ) : (
              <button
                type="button"
                onClick={() => onStartEdit("flight_number")}
                class={`${CLICKABLE_CELL} font-medium text-gray-900 dark:text-gray-100`}
                aria-label={`Edit flight number (currently ${row.flight_number})`}
              >
                {row.flight_number}
              </button>
            )}
            {(editing === "route_leg" || row.route_leg !== null) && (
              <>
                <span aria-hidden="true" class="text-xs text-gray-400 dark:text-gray-500">
                  /L.
                </span>
                {editing === "route_leg" ? (
                  <RouteLegEditor
                    initial={row.route_leg ?? ""}
                    onCommit={onCommitRouteLeg}
                    onCancel={onCancelEdit}
                  />
                ) : (
                  <button
                    type="button"
                    onClick={() => onStartEdit("route_leg")}
                    class={`${CLICKABLE_CELL} text-sm text-gray-700 dark:text-gray-200`}
                    aria-label={`Edit route leg (currently ${row.route_leg ?? "none"})`}
                  >
                    {row.route_leg}
                  </button>
                )}
              </>
            )}
          </span>
          <span class="font-mono text-xs text-gray-700 dark:text-gray-200">
            {row.dpt_airport_id}
            <span class="px-1 text-gray-400">→</span>
            {row.arr_airport_id}
          </span>
          {directionBadge}
        </div>

        {/* Line 2: schedule + distance + days */}
        <div class="flex flex-wrap items-baseline gap-x-3 font-mono text-xs text-gray-500 dark:text-gray-400">
          <span class="inline-flex items-baseline gap-1">
            <span class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
              Dpt
            </span>
            {editing === "departure_time" ? (
              <DepartureTimeEditor
                initial={row.departure_time}
                onCommit={onCommitDepartureTime}
                onCancel={onCancelEdit}
              />
            ) : (
              <button
                type="button"
                onClick={() => onStartEdit("departure_time")}
                class={CLICKABLE_CELL}
                aria-label={`Edit departure time (currently ${row.departure_time})`}
              >
                {row.departure_time}
              </button>
            )}
          </span>
          <span class="inline-flex items-baseline gap-1">
            <span class="text-[10px] uppercase tracking-wide text-gray-400 dark:text-gray-500">
              Arr
            </span>
            <span>{arrLabel}</span>
          </span>
          <span aria-hidden="true">·</span>
          <span>{row.distance_nm.toFixed(1)} nm</span>
          <span aria-hidden="true">·</span>
          <span>{dayLabel}</span>
        </div>
      </div>

      {/* Col 4: lint icon */}
      <div role="gridcell" class="flex justify-end pt-0.5">
        <RowLintIcon rowIndex={row.index} />
      </div>
    </div>
  );
}

type FlightNumberEditorProps = {
  initial: number;
  onCommit: (raw: string) => void;
  onCancel: () => void;
};

function FlightNumberEditor({ initial, onCommit, onCancel }: FlightNumberEditorProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [val, setVal] = useState<string>(String(initial));

  useEffect(() => {
    inputRef.current?.focus();
    inputRef.current?.select();
  }, []);

  return (
    <input
      ref={inputRef}
      type="number"
      min={1}
      step={1}
      value={val}
      onInput={(e) => setVal((e.currentTarget as HTMLInputElement).value)}
      onBlur={() => onCommit(val)}
      onKeyDown={(e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          onCommit(val);
        } else if (e.key === "Escape") {
          e.preventDefault();
          onCancel();
        }
      }}
      class="w-20 rounded border border-primary-500 bg-white px-1 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-900 dark:text-gray-100"
    />
  );
}

type RouteLegEditorProps = {
  initial: string;
  onCommit: (raw: string) => void;
  onCancel: () => void;
};

function RouteLegEditor({ initial, onCommit, onCancel }: RouteLegEditorProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [val, setVal] = useState<string>(initial);

  useEffect(() => {
    inputRef.current?.focus();
    inputRef.current?.select();
  }, []);

  return (
    <input
      ref={inputRef}
      type="number"
      // min=0 (not 1) so the user can type 0 to intentionally clear the
      // leg. commitRouteLeg() maps 0/empty/negative to null per the
      // server's canonicalizeRoutePart() rules.
      min={0}
      step={1}
      value={val}
      onInput={(e) => setVal((e.currentTarget as HTMLInputElement).value)}
      onBlur={() => onCommit(val)}
      onKeyDown={(e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          onCommit(val);
        } else if (e.key === "Escape") {
          e.preventDefault();
          onCancel();
        }
      }}
      class="w-14 rounded border border-primary-500 bg-white px-1 py-0.5 text-sm focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-900 dark:text-gray-100"
    />
  );
}

type DepartureTimeEditorProps = {
  initial: string;
  onCommit: (time: string) => void;
  onCancel: () => void;
};

function DepartureTimeEditor({ initial, onCommit, onCancel }: DepartureTimeEditorProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [val, setVal] = useState<string>(initial);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  return (
    <input
      ref={inputRef}
      type="time"
      value={val}
      onInput={(e) => setVal((e.currentTarget as HTMLInputElement).value)}
      onBlur={() => onCommit(val)}
      onKeyDown={(e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          onCommit(val);
        } else if (e.key === "Escape") {
          e.preventDefault();
          onCancel();
        }
      }}
      class="rounded border border-primary-500 bg-white px-1 py-0.5 font-mono text-xs focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-gray-900 dark:text-gray-100"
    />
  );
}

function formatDaysMask(mask: number): string {
  return DAY_LETTERS.map((letter, i) => ((mask & (1 << i)) !== 0 ? letter : "·")).join("");
}
