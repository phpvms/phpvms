/**
 * Cross-cutting lifecycle effects + the canonical `regenerateRows()` action.
 *
 * Two `@preact/signals` effects are registered by `setupLifecycle()`:
 *
 *   - **Dirty tracker** — compares a hash of the generator-affecting form
 *     fields against the last-generated snapshot. When they diverge AND
 *     `rows.length > 0`, `formStaleSinceGenerate` flips true (PreviewPanel
 *     renders a banner). When that happens AND any row carries `edited: true`,
 *     `dirtyDialogOpen` flips true so DirtyWarningDialog mounts and asks
 *     before nuking user edits. Bundle-config fields (name, description,
 *     dates, fare_multiplier) are intentionally NOT in the fingerprint —
 *     they don't affect rows.
 *
 *   - **Auto-lint** — recomputes `lintReport` whenever `form` or `rows`
 *     change (and `rows.length > 0`). Synchronous, in-memory; runLint is
 *     cheap for the ≤100-row v1 cap so no debounce. Server-side lint at
 *     /lint stays authoritative; this is live UX feedback only.
 *
 * `regenerateRows()` is the single entry point for materializing rows from
 * the current form. Called by PreviewPanel's Generate button and by
 * DirtyWarningDialog's Confirm Regenerate action. Updates the dirty
 * tracker's fingerprint baseline so the form stops appearing stale.
 *
 * `setupLifecycle()` is idempotent — calling twice is a no-op. App.tsx
 * arms it inside the same gating useEffect as persistence (after the
 * resume-banner flow settles).
 */

import { effect, signal } from "@preact/signals";

import { generate } from "./generator";
import { type LintContext, runLint } from "./lint";
import { airlineStats, airportCache, form, lintReport, rows, subfleetCache } from "../state/store";
import type { AirportSummary, Form, SubfleetSummary } from "../state/types";

// ─── Public signals ───────────────────────────────────────────────────────

/** True when the form has changed since the last Generate AND rows still exist. */
export const formStaleSinceGenerate = signal<boolean>(false);

/** True when the dirty tracker wants confirmation before nuking edits. */
export const dirtyDialogOpen = signal<boolean>(false);

// ─── Internals ────────────────────────────────────────────────────────────

let lastGeneratedFingerprint: string | null = null;
let lifecycleArmed = false;

/**
 * Serialize the subset of form that affects generator output. Bundle config
 * is excluded — changing the bundle name doesn't invalidate rows.
 */
function fingerprint(f: Form): string {
  return JSON.stringify({
    airline_id: f.airline_id,
    topology: f.topology,
    mode: f.mode,
    origins: f.origins,
    destinations: f.destinations,
    create_returns: f.create_returns,
    subfleet_ids: f.subfleet_ids,
    flight_type: f.flight_type,
    event_id: f.event_id,
    days_mask: f.days_mask,
    time_strategy: f.time_strategy,
    flight_number_strategy: f.flight_number_strategy,
  });
}

function readServerConfig() {
  return window.routeforgeConfig?.config ?? {};
}

function buildLintContext(): LintContext {
  const f = form.value;
  const cfg = readServerConfig();
  const subfleets: SubfleetSummary[] = f.subfleet_ids
    .map((id) => subfleetCache.value[id])
    .filter((s): s is SubfleetSummary => s !== undefined);
  return {
    rows: rows.value,
    selected_subfleets: subfleets,
    flight_type: f.flight_type,
    event: null,
    bundle_start_date: f.bundle.start_date,
    bundle_end_date: f.bundle.end_date,
    airline_stats: airlineStats.value ?? {
      existing_active_flights_count: 0,
      hub_airports: [],
    },
    mesh_warn_count: typeof cfg.mesh_warn_count === "number" ? cfg.mesh_warn_count : undefined,
    mesh_max_count: typeof cfg.mesh_max_count === "number" ? cfg.mesh_max_count : undefined,
  };
}

// ─── Public API ───────────────────────────────────────────────────────────

/**
 * Register lifecycle effects. Returns a disposer for use as useEffect cleanup.
 * Safe to call multiple times — second call is a no-op until the first
 * disposer runs.
 */
export function setupLifecycle(): () => void {
  if (lifecycleArmed) {
    return () => {};
  }
  lifecycleArmed = true;

  const disposeDirty = effect(() => {
    const f = form.value;
    const r = rows.value;
    // No baseline yet → user hasn't generated. Nothing can be stale.
    if (lastGeneratedFingerprint === null) {
      return;
    }
    if (r.length === 0) {
      // Rows cleared (post-commit reset). Drop the stale flag.
      formStaleSinceGenerate.value = false;
      return;
    }
    const fp = fingerprint(f);
    const stale = fp !== lastGeneratedFingerprint;
    formStaleSinceGenerate.value = stale;
    if (stale && r.some((row) => row.edited)) {
      dirtyDialogOpen.value = true;
    }
  });

  const disposeAutoLint = effect(() => {
    // Subscribe to rows + the form fields that lint reads.
    const r = rows.value;
    // Touch form via fingerprint so any generator-affecting change re-runs.
    // We also touch bundle dates because L8 reads them.
    void form.value.bundle.start_date;
    void form.value.bundle.end_date;
    if (r.length === 0) {
      lintReport.value = null;
      return;
    }
    lintReport.value = runLint(buildLintContext());
  });

  return () => {
    disposeDirty();
    disposeAutoLint();
    lifecycleArmed = false;
  };
}

/**
 * Build the airport + subfleet Maps generator.ts wants and run it.
 * Updates rows + the dirty tracker baseline. No-op when airline is unset.
 */
export function regenerateRows(): void {
  const f = form.value;
  if (f.airline_id === null) {
    return;
  }

  const airportsMap = new Map<string, AirportSummary>();
  for (const [icao, summary] of Object.entries(airportCache.value)) {
    airportsMap.set(icao, summary);
  }

  const subfleetsMap = new Map<number, SubfleetSummary>();
  for (const [idStr, summary] of Object.entries(subfleetCache.value)) {
    subfleetsMap.set(Number(idStr), summary);
  }

  const newRows = generate({
    form: f,
    airports: airportsMap,
    subfleets: subfleetsMap,
    options: {
      gen_date: new Date(),
      server_config: readServerConfig(),
    },
  });

  rows.value = newRows;
  lastGeneratedFingerprint = fingerprint(f);
  formStaleSinceGenerate.value = false;
  dirtyDialogOpen.value = false;
}

/**
 * Reset the dirty tracker — called by handleDiscard and after a successful
 * commit so the next session starts with no baseline.
 */
export function resetLifecycleState(): void {
  lastGeneratedFingerprint = null;
  formStaleSinceGenerate.value = false;
  dirtyDialogOpen.value = false;
}
