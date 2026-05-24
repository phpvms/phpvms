/**
 * Subfleet multi-select.
 *
 * Pre-populates from `/admin/route-forge/api/subfleets?airline_id=N` whenever
 * the airline changes. Results land in `subfleetCache` (so a resume from
 * draft doesn't have to refetch) and the live <select multiple> binds to
 * `form.subfleet_ids`.
 *
 * No capability filter (Decision 7): every subfleet for the chosen airline
 * shows. Lint rules L2 (range mismatch) and L2b (type mismatch) surface
 * incompatibilities after the user generates rows.
 *
 * When the airline_id changes:
 *   - Clear any previously selected subfleet IDs (subfleets are airline-scoped).
 *   - Wipe the cache for the prior airline's subfleets (cheap; the cache
 *     refills on next render).
 *   - Fetch the new airline's subfleet list.
 */

import { useEffect, useRef, useState } from "preact/hooks";

import { ApiError, getSubfleets } from "../lib/api";
import { form, subfleetCache } from "../state/store";
import type { SubfleetSummary } from "../state/types";
import { Field, INPUT_CLASS } from "./Field";

type LoadState =
  | { kind: "idle" }
  | { kind: "loading" }
  | { kind: "loaded"; subfleets: SubfleetSummary[] }
  | { kind: "error"; message: string };

export function SubfleetPicker() {
  const f = form.value;
  const airlineId = f.airline_id;
  const [state, setState] = useState<LoadState>({ kind: "idle" });
  // Tracks the airline this component last loaded subfleets for. Stays null
  // on first mount so draft resume (which arrives with airline_id and
  // subfleet_ids already populated) does NOT clear the persisted selections.
  // Subsequent airline changes do clear them, preserving the invariant
  // "subfleet_ids belong to airline_id".
  const prevAirlineId = useRef<number | null>(null);

  useEffect(() => {
    if (prevAirlineId.current !== null && prevAirlineId.current !== airlineId) {
      if (form.value.subfleet_ids.length > 0) {
        form.value = { ...form.value, subfleet_ids: [] };
      }
    }
    prevAirlineId.current = airlineId;

    if (airlineId === null) {
      setState({ kind: "idle" });
      return;
    }
    let cancelled = false;
    setState({ kind: "loading" });
    getSubfleets({ airline_id: airlineId })
      .then((res) => {
        if (cancelled) {
          return;
        }
        const list = res.data;
        // Populate cache so generator.ts + lint can resolve subfleet
        // metadata by id without an extra request.
        const next: Record<number, SubfleetSummary> = { ...subfleetCache.value };
        for (const s of list) {
          next[s.id] = s;
        }
        subfleetCache.value = next;
        setState({ kind: "loaded", subfleets: list });
      })
      .catch((err: unknown) => {
        if (cancelled) {
          return;
        }
        const message =
          err instanceof ApiError
            ? `Failed to load subfleets (HTTP ${err.status}).`
            : "Failed to load subfleets.";
        setState({ kind: "error", message });
      });
    return () => {
      cancelled = true;
    };
  }, [airlineId]);

  function handleChange(e: Event): void {
    const select = e.currentTarget as HTMLSelectElement;
    const ids: number[] = [];
    for (const opt of Array.from(select.selectedOptions)) {
      const id = Number.parseInt(opt.value, 10);
      if (!Number.isNaN(id)) {
        ids.push(id);
      }
    }
    form.value = { ...f, subfleet_ids: ids };
  }

  const baseHint =
    "Hold Cmd / Ctrl to multi-select. Lint warns about range or type mismatches after generation.";

  if (airlineId === null) {
    return (
      <Field label="Subfleets" hint="Pick an airline first.">
        <select class={INPUT_CLASS} disabled multiple size={4} />
      </Field>
    );
  }

  if (state.kind === "loading") {
    return (
      <Field label="Subfleets" hint="Loading…">
        <select class={INPUT_CLASS} disabled multiple size={4} />
      </Field>
    );
  }

  if (state.kind === "error") {
    return (
      <Field label="Subfleets" error={state.message}>
        <select class={INPUT_CLASS} disabled multiple size={4} />
      </Field>
    );
  }

  if (state.kind === "idle") {
    // Shouldn't normally reach this branch when airlineId is set.
    return (
      <Field label="Subfleets" hint="Loading…">
        <select class={INPUT_CLASS} disabled multiple size={4} />
      </Field>
    );
  }

  return (
    <Field label="Subfleets" htmlFor="rf-subfleets" hint={baseHint}>
      <select
        id="rf-subfleets"
        class={INPUT_CLASS}
        multiple
        size={Math.min(8, Math.max(4, state.subfleets.length))}
        onChange={handleChange}
      >
        {state.subfleets.length === 0 && (
          <option disabled value="">
            No subfleets attached to this airline.
          </option>
        )}
        {state.subfleets.map((s) => {
          const selected = f.subfleet_ids.includes(s.id);
          return (
            <option key={s.id} value={s.id} selected={selected}>
              {s.name} ({s.type}) · {s.aircraft_count} ac
              {s.max_range_nm !== null ? ` · ${s.max_range_nm}nm` : ""}
            </option>
          );
        })}
      </select>
    </Field>
  );
}
