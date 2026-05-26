/**
 * Airport multi-select with click-to-open dropdown + checkbox rows.
 *
 * Renders twice — once with mode="origin", once with mode="destination" —
 * each bound to the matching `form.origins` / `form.destinations` array. The
 * destination instance excludes airports already chosen as origins, matching
 * the spec scenario "destination picker hides already-selected origins".
 *
 * Interaction model:
 *
 *   - Click the input → dropdown opens and the initial alpha-sorted page of
 *     airports loads (no query needed).
 *   - Type ≥1 character → debounced re-fetch with `search=<query>`.
 *   - Each row is a `<label>` wrapping a checkbox + display text. Click the
 *     row OR the checkbox to toggle membership in the selected list. Already-
 *     selected items stay in the dropdown with the checkbox CHECKED so the
 *     user can see what's already in without deselecting + re-finding.
 *   - Selected airports also render as chips above the input (the standard
 *     multi-select look). Chip × removes the airport — same effect as
 *     unchecking the row.
 *   - Dropdown stays open across selections. Clicks outside the picker
 *     container close it; Escape closes it; the chevron toggle button also
 *     closes/opens manually.
 *
 * Bug fix landing in this rewrite: previous version sent `q=…` to
 * /preview-airports but the backend (App\Queries\AirportSearchQueryV1) reads
 * `search=…`. The typeahead was never actually filtering server-side —
 * every keystroke returned the unfiltered first page. The api.ts param is
 * now `search` and matches the server contract.
 *
 * Error handling: API errors silently produce empty results. The user can
 * close + reopen the dropdown to retry. A toast surface is a v2 polish item.
 */

import { useEffect, useMemo, useRef, useState } from "preact/hooks";

import { getPreviewAirports } from "../lib/api";
import { t } from "../lib/i18n";
import { airportCache, form } from "../state/store";
import type { AirportSummary, Icao } from "../state/types";
import { Field, INPUT_CLASS } from "./Field";

export type AirportPickerMode = "origin" | "destination";

export type AirportPickerProps = {
  mode: AirportPickerMode;
  /**
   * Mutes the picker (input disabled, dropdown suppressed, chip removal
   * suppressed). Used when a topology doesn't consume this list — e.g.
   * Tour (internal "tour") ignores destinations. Existing selections stay
   * visible so the user can see what they had if they switch topologies back.
   */
  disabled?: boolean;
  /** Hint override; rendered in place of the default per-mode hint. */
  hint?: string;
};

const DEBOUNCE_MS = 250;
const RESULT_LIMIT = 50;
const BLUR_CLOSE_DELAY_MS = 150;

export function AirportPicker({ mode, disabled = false, hint: hintOverride }: AirportPickerProps) {
  const f = form.value;
  const selected: Icao[] = mode === "origin" ? f.origins : f.destinations;
  // Destination picker hides already-selected origins per spec.
  const hideFromResults: Set<Icao> = useMemo(() => {
    const set = new Set<Icao>();
    if (mode === "destination") {
      for (const o of f.origins) {
        set.add(o);
      }
    }
    return set;
  }, [mode, f.origins]);

  const [query, setQuery] = useState<string>("");
  const [results, setResults] = useState<AirportSummary[]>([]);
  const [loading, setLoading] = useState<boolean>(false);
  const [open, setOpen] = useState<boolean>(false);
  const [hasFetchedInitial, setHasFetchedInitial] = useState<boolean>(false);
  const containerRef = useRef<HTMLDivElement | null>(null);

  // Force dropdown closed whenever the picker becomes disabled. Prevents a
  // stale-open dropdown from lingering after a topology switch that mutes
  // this picker.
  useEffect(() => {
    if (disabled && open) {
      setOpen(false);
    }
  }, [disabled, open]);

  // Debounced typeahead fetch. Fires on:
  //   - any query change (typed filtering)
  //   - the first time the dropdown opens (initial alpha-sorted page)
  useEffect(() => {
    if (!open) {
      return;
    }
    // If query is empty AND we've already loaded the initial page,
    // don't refetch (the results stay valid until the user types).
    const trimmed = query.trim();
    if (trimmed === "" && hasFetchedInitial) {
      return;
    }
    setLoading(true);
    let cancelled = false;
    const timer = setTimeout(() => {
      const params: { search?: string; searchMode?: "prefix"; limit: number } = {
        limit: RESULT_LIMIT,
      };
      if (trimmed !== "") {
        params.search = trimmed;
        params.searchMode = "prefix";
      }
      getPreviewAirports(params)
        .then((res) => {
          if (cancelled) {
            return;
          }
          const list = res.data;
          if (list.length > 0) {
            const next: Record<Icao, AirportSummary> = { ...airportCache.value };
            for (const a of list) {
              next[a.id] = a;
            }
            airportCache.value = next;
          }
          setResults(list);
          if (trimmed === "") {
            setHasFetchedInitial(true);
          }
        })
        .catch(() => {
          if (cancelled) {
            return;
          }
          setResults([]);
        })
        .finally(() => {
          if (!cancelled) {
            setLoading(false);
          }
        });
    }, DEBOUNCE_MS);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [query, open, hasFetchedInitial]);

  // Click-outside detector: close the dropdown when the user clicks
  // anywhere outside the picker's container. Stays attached only while
  // the dropdown is open so we don't pay for the listener otherwise.
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

  function toggleAirport(icao: Icao): void {
    if (disabled) {
      return;
    }
    const next = selected.includes(icao) ? selected.filter((x) => x !== icao) : [...selected, icao];
    form.value = mode === "origin" ? { ...f, origins: next } : { ...f, destinations: next };
  }

  function removeAirport(icao: Icao): void {
    if (disabled) {
      return;
    }
    const next = selected.filter((x) => x !== icao);
    form.value = mode === "origin" ? { ...f, origins: next } : { ...f, destinations: next };
  }

  const label = mode === "origin" ? "Origins" : "Destinations";
  const defaultHint =
    mode === "origin"
      ? "Click to browse or type to filter. Tick to add."
      : "Click to browse or type to filter. Already-selected origins are hidden.";
  const hint = hintOverride ?? defaultHint;
  const inputId = `rf-airport-${mode}`;

  const visibleResults = results.filter((a) => !hideFromResults.has(a.id));

  return (
    <Field label={label} htmlFor={inputId} hint={hint} required>
      <div class={disabled ? "opacity-60" : undefined} aria-disabled={disabled || undefined}>
        {/* Selected chips */}
        {selected.length > 0 && (
          <div class="mb-2 flex flex-wrap gap-1.5">
            {selected.map((icao) => {
              const a = airportCache.value[icao];
              const display = a !== undefined && a.name !== "" ? `${icao} — ${a.name}` : icao;
              return (
                <span
                  key={icao}
                  class="inline-flex items-center gap-1 rounded bg-primary-100 px-2 py-0.5 text-xs text-primary-800 dark:bg-primary-900/40 dark:text-primary-200"
                >
                  <span class="font-mono">{display}</span>
                  {!disabled && (
                    <button
                      type="button"
                      aria-label={`Remove ${icao}`}
                      class="text-primary-600 hover:text-red-600 dark:text-primary-300 dark:hover:text-red-400"
                      onClick={() => removeAirport(icao)}
                    >
                      ×
                    </button>
                  )}
                </span>
              );
            })}
          </div>
        )}

        {/* Search input + dropdown */}
        <div class="relative" ref={containerRef}>
          <input
            id={inputId}
            type="search"
            autocomplete="off"
            placeholder={t("airport_picker.placeholder")}
            class={`${INPUT_CLASS} ${disabled ? "cursor-not-allowed" : ""}`}
            value={query}
            disabled={disabled}
            onInput={(e) => {
              if (disabled) {
                return;
              }
              setQuery((e.currentTarget as HTMLInputElement).value);
              setOpen(true);
            }}
            onFocus={() => {
              if (disabled) {
                return;
              }
              setOpen(true);
            }}
            onClick={() => {
              if (disabled) {
                return;
              }
              setOpen(true);
            }}
            onKeyDown={(e) => {
              if (e.key === "Escape") {
                setOpen(false);
                (e.currentTarget as HTMLInputElement).blur();
              }
            }}
            onBlur={() => {
              // Defer so an item click inside the dropdown registers
              // before close. The click-outside handler does the
              // primary closing work; this is a fallback for tab-out.
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
          {open && (
            <div class="absolute z-10 mt-1 max-h-72 w-full overflow-auto rounded border border-gray-300 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
              {loading && results.length === 0 && (
                <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">Loading…</div>
              )}
              {!loading && visibleResults.length === 0 && (
                <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                  {query.trim() === "" ? "No airports available." : "No matches."}
                </div>
              )}
              {visibleResults.map((a) => {
                const isSelected = selected.includes(a.id);
                return (
                  <label
                    key={a.id}
                    class="flex w-full cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-primary-50 dark:hover:bg-primary-900/30"
                  >
                    <input
                      type="checkbox"
                      class="h-4 w-4"
                      checked={isSelected}
                      onChange={() => toggleAirport(a.id)}
                    />
                    <span class="font-mono">{a.id}</span>
                    {a.name !== "" && (
                      <span class="text-gray-600 dark:text-gray-400">{a.name}</span>
                    )}
                    {a.country !== null && (
                      <span class="text-xs text-gray-400 dark:text-gray-500">· {a.country}</span>
                    )}
                    {a.hub && (
                      <span class="ml-auto rounded bg-primary-200 px-1 text-[10px] text-primary-800 dark:bg-primary-800 dark:text-primary-100">
                        HUB
                      </span>
                    )}
                  </label>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </Field>
  );
}
