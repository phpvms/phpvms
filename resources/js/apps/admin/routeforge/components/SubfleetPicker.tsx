/**
 * Subfleet multi-select with click-to-open dropdown + checkbox rows.
 *
 * Mirrors AirportPicker's interaction model (chips + searchable dropdown +
 * checkboxes) so the form's two multi-selects feel identical. Differences
 * from AirportPicker:
 *
 *   - Source list is bounded (one airline's subfleets) and already arrives
 *     in a single fetch, so filtering is purely client-side: no debounce,
 *     no per-keystroke network round-trip.
 *   - Chips render from `subfleetCache` (not the load result) so persisted
 *     draft selections stay visible during the load → loaded transition
 *     when an airline change refetches.
 *
 * Pre-populates from `/admin/route-forge/api/subfleets?airline_id=N` whenever
 * the airline changes. Results land in `subfleetCache` (so a resume from
 * draft doesn't have to refetch) and `form.subfleet_ids` is the source of
 * truth for selection.
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

const BLUR_CLOSE_DELAY_MS = 150;

export function SubfleetPicker() {
  const f = form.value;
  const airlineId = f.airline_id;
  const [state, setState] = useState<LoadState>({ kind: "idle" });
  const [query, setQuery] = useState<string>("");
  const [open, setOpen] = useState<boolean>(false);
  const containerRef = useRef<HTMLDivElement | null>(null);
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

  // Click-outside detector: close the dropdown when the user clicks
  // anywhere outside the picker's container. Attached only while open.
  useEffect(() => {
    if (!open) {
      return undefined;
    }
    function onDocPointerDown(event: PointerEvent): void {
      const target = event.target;
      if (target === null || !(target instanceof Node)) {
        return;
      }
      if (containerRef.current !== null && !containerRef.current.contains(target)) {
        setOpen(false);
      }
    }
    document.addEventListener("pointerdown", onDocPointerDown);
    return () => document.removeEventListener("pointerdown", onDocPointerDown);
  }, [open]);

  function toggleSubfleet(id: number): void {
    // Read freshest form value at apply time so concurrent edits survive.
    const current = form.value;
    const next = current.subfleet_ids.includes(id)
      ? current.subfleet_ids.filter((x) => x !== id)
      : [...current.subfleet_ids, id];
    form.value = { ...current, subfleet_ids: next };
  }

  function removeSubfleet(id: number): void {
    const current = form.value;
    form.value = {
      ...current,
      subfleet_ids: current.subfleet_ids.filter((x) => x !== id),
    };
  }

  // Early-return branches that don't render the searchable dropdown.
  if (airlineId === null) {
    return (
      <Field label="Subfleets" htmlFor="rf-subfleets" hint="Pick an airline first.">
        <input
          id="rf-subfleets"
          type="search"
          disabled
          placeholder="Pick an airline first…"
          class={INPUT_CLASS}
        />
      </Field>
    );
  }

  if (state.kind === "error") {
    return (
      <Field label="Subfleets" htmlFor="rf-subfleets" error={state.message}>
        <input id="rf-subfleets" type="search" disabled class={INPUT_CLASS} />
      </Field>
    );
  }

  const loading = state.kind === "loading" || state.kind === "idle";
  const subfleets = state.kind === "loaded" ? state.subfleets : [];
  const trimmedQuery = query.trim().toLowerCase();
  const filteredResults =
    trimmedQuery === ""
      ? subfleets
      : subfleets.filter(
          (s) =>
            s.name.toLowerCase().includes(trimmedQuery) ||
            s.type.toLowerCase().includes(trimmedQuery),
        );
  const noSubfleetsAtAll = state.kind === "loaded" && subfleets.length === 0;
  const selected = f.subfleet_ids;

  const hint =
    "Click to browse or type to filter. Tick to add. Lint warns about range or type mismatches after generation.";

  return (
    <Field label="Subfleets" htmlFor="rf-subfleets" hint={hint}>
      {/* Selected chips — render from cache so they survive load transitions. */}
      {selected.length > 0 && (
        <div class="mb-2 flex flex-wrap gap-1.5">
          {selected.map((id) => {
            const s = subfleetCache.value[id];
            const display = s !== undefined ? `${s.name} (${s.type})` : `#${id}`;
            return (
              <span
                key={id}
                class="inline-flex items-center gap-1 rounded bg-primary-100 px-2 py-0.5 text-xs text-primary-800 dark:bg-primary-900/40 dark:text-primary-200"
              >
                <span>{display}</span>
                <button
                  type="button"
                  aria-label={`Remove ${display}`}
                  class="text-primary-600 hover:text-red-600 dark:text-primary-300 dark:hover:text-red-400"
                  onClick={() => removeSubfleet(id)}
                >
                  ×
                </button>
              </span>
            );
          })}
        </div>
      )}

      {/* Search input + dropdown */}
      <div class="relative" ref={containerRef}>
        <input
          id="rf-subfleets"
          type="search"
          autocomplete="off"
          placeholder={loading ? "Loading subfleets…" : "Subfleet name or type…"}
          disabled={loading}
          class={INPUT_CLASS}
          value={query}
          onInput={(e) => {
            setQuery((e.currentTarget as HTMLInputElement).value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onClick={() => setOpen(true)}
          onKeyDown={(e) => {
            if (e.key === "Escape") {
              setOpen(false);
              (e.currentTarget as HTMLInputElement).blur();
            }
          }}
          onBlur={() => {
            // Defer so an item click inside the dropdown registers
            // before close. Click-outside handler does the primary
            // closing work; this is a fallback for tab-out.
            setTimeout(() => {
              const active = document.activeElement;
              if (
                containerRef.current !== null &&
                active !== null &&
                containerRef.current.contains(active)
              ) {
                return;
              }
              setOpen(false);
            }, BLUR_CLOSE_DELAY_MS);
          }}
        />
        {open && !loading && (
          <div class="absolute z-10 mt-1 max-h-72 w-full overflow-auto rounded border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
            {noSubfleetsAtAll && (
              <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                No subfleets attached to this airline.
              </div>
            )}
            {!noSubfleetsAtAll && filteredResults.length === 0 && (
              <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">No matches.</div>
            )}
            {filteredResults.map((s) => {
              const isSelected = selected.includes(s.id);
              return (
                <label
                  key={s.id}
                  class="flex w-full cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-primary-50 dark:hover:bg-primary-900/30"
                >
                  <input
                    type="checkbox"
                    class="h-4 w-4"
                    checked={isSelected}
                    onChange={() => toggleSubfleet(s.id)}
                  />
                  <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{s.type}</span>
                  <span>{s.name}</span>
                  <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">
                    {s.aircraft_count} ac
                    {s.max_range_nm !== null ? ` · ${s.max_range_nm}nm` : ""}
                  </span>
                </label>
              );
            })}
          </div>
        )}
      </div>
    </Field>
  );
}
