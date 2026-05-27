/**
 * Cross-cutting lifecycle effects + the canonical `regenerateRows()` action.
 *
 * Three `@preact/signals` effects are registered by `setupLifecycle()`:
 *
 *   - **Dirty tracker** — compares a hash of the generator-affecting form
 *     fields against the last-generated snapshot. When they diverge AND
 *     `rows.length > 0` AND any row carries `edited: true`, `dirtyDialogOpen`
 *     flips true so DirtyWarningDialog mounts and asks before nuking user
 *     edits. `formStaleSinceGenerate` mirrors the diverged-fingerprint state
 *     for header banners. Bundle-config fields (name, description, dates,
 *     fare_multiplier) are intentionally NOT in the fingerprint — they
 *     don't affect rows.
 *
 *   - **Auto-regenerate (debounced)** — when the form is generate-eligible
 *     (airline + airports + topology constraints satisfied) AND no row
 *     carries `edited: true`, schedules `regenerateRows()` after a short
 *     debounce. This is the reactive UX: type in the form, rows refresh.
 *     The dirty tracker still handles the "user has edits" case — it shows
 *     the confirmation dialog instead of silently nuking work. First-ever
 *     generation also runs through here as soon as the form fills out.
 *
 *   - **Auto-lint (debounced server call)** — when `form` or `rows` change
 *     and there is enough data to lint (airline_id set, rows.length > 0),
 *     schedules a debounced (`DEBOUNCE_MS`) POST to `/admin/route-forge/api/lint`.
 *     The response replaces `lintReport`; an `ApiError` (network or 422
 *     form-validation) clears the report and surfaces via `lintError` so
 *     PreviewPanel can show a non-blocking hint. In-flight requests are
 *     aborted when a newer change arrives (AbortController). The server is
 *     the sole authority for the L1–L11 catalog — see the Section 6
 *     decision-change banner in tasks.md.
 *
 * `regenerateRows()` is the single entry point for materializing rows from
 * the current form. Called by the auto-regen effect, by PreviewPanel's
 * Regenerate button, and by DirtyWarningDialog's Confirm Regenerate action.
 * Updates the dirty tracker's fingerprint baseline so the form stops
 * appearing stale.
 *
 * `buildLintPayload()` is also exported so PreviewPanel's Create-click can
 * use the same envelope shape as the background auto-lint call.
 *
 * `setupLifecycle()` is idempotent — calling twice is a no-op. App.tsx
 * arms it inside the same gating useEffect as persistence (after the
 * resume-banner flow settles).
 */

import { effect, signal } from "@preact/signals";

import { ApiError, postLint } from "./api";
import { generate } from "./generator";
import { getBootOrThrow } from "../state/boot";
import {
  airlineStats,
  airportCache,
  form,
  lintError,
  lintReport,
  rows,
  subfleetCache,
} from "../state/store";
import type {
  AirportSummary,
  Form,
  LintPayload,
  PayloadRow,
  Row,
  SubfleetSummary,
} from "../state/types";

// ─── Public signals ───────────────────────────────────────────────────────

/** True when the form has changed since the last Generate AND rows still exist. */
export const formStaleSinceGenerate = signal<boolean>(false);

/** True when the dirty tracker wants confirmation before nuking edits. */
export const dirtyDialogOpen = signal<boolean>(false);

// ─── Internals ────────────────────────────────────────────────────────────

/**
 * Debounce window for the background `/lint` call. Picked to feel "live"
 * without flooding the server during fast typing. Exported for tests.
 */
export const DEBOUNCE_MS = 400;

/**
 * Debounce window for the auto-regenerate effect. Shorter than `/lint`
 * because regen is local (cheap) and produces the rows that lint then
 * consumes — running it first lets lint see the fresh rows.
 */
export const AUTO_REGEN_DEBOUNCE_MS = 200;

let lastGeneratedFingerprint: string | null = null;
let lifecycleArmed = false;

// Background-lint state (module-level so the disposer can clean up).
let lintDebounceTimer: ReturnType<typeof setTimeout> | null = null;
let lintInflightController: AbortController | null = null;
let lintRequestToken = 0;

// Auto-regen state (module-level so the disposer can cancel a pending run).
let regenDebounceTimer: ReturnType<typeof setTimeout> | null = null;

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
  return getBootOrThrow().config;
}

function toPayloadRow(r: Row): PayloadRow {
  const { edited: _edited, ...rest } = r;
  return rest;
}

/**
 * Snapshot the current form + rows into the `/lint` wire shape. Returns
 * null when the envelope can't be linted yet — no airline picked, or the
 * server-required `origins` / `destinations` lists are empty (the server's
 * `BaseRouteForgeBatchRequest` enforces both as `required|array|min:1`).
 * Exported so PreviewPanel's Create-click reuses the same envelope.
 */
export function buildLintPayload(): LintPayload | null {
  const f = form.value;
  if (f.airline_id === null) {
    return null;
  }
  if (f.origins.length === 0 || f.destinations.length === 0) {
    return null;
  }
  return {
    airline_id: f.airline_id,
    event_id: f.event_id,
    subfleet_ids: f.subfleet_ids,
    flight_type: f.flight_type,
    bundle: f.bundle,
    origins: f.origins,
    destinations: f.destinations,
    rows: rows.value.map(toPayloadRow),
  };
}

/**
 * Mirror of PreviewPanel's `describeGenerateBlocker` — returns true when
 * the form has the minimum data to materialize rows. Used to gate the
 * auto-regenerate effect.
 */
function isGenerateEligible(f: Form): boolean {
  if (f.airline_id === null) {
    return false;
  }
  if (f.origins.length === 0) {
    return false;
  }
  if (f.topology === "tour") {
    return f.origins.length >= 2;
  }
  return f.destinations.length > 0;
}

/**
 * Cancel any pending debounce timer + in-flight fetch. Used when an effect
 * tear-down runs or when a newer change supersedes the previous one.
 */
function cancelInflightLint(): void {
  if (lintDebounceTimer !== null) {
    clearTimeout(lintDebounceTimer);
    lintDebounceTimer = null;
  }
  if (lintInflightController !== null) {
    lintInflightController.abort();
    lintInflightController = null;
  }
}

/**
 * Cancel any pending auto-regen timer. Called by the effect when it
 * decides regen isn't safe (or eligible) right now, and by the disposer.
 */
function cancelPendingRegen(): void {
  if (regenDebounceTimer !== null) {
    clearTimeout(regenDebounceTimer);
    regenDebounceTimer = null;
  }
}

/**
 * Drive the background `/lint` call. Each invocation bumps a token so a
 * late-resolving response from a stale request can't clobber a fresher
 * one (belt-and-braces alongside AbortController, since aborted fetches
 * still race with the response handler in some browsers).
 */
function scheduleLint(payload: LintPayload): void {
  cancelInflightLint();
  const token = ++lintRequestToken;
  lintDebounceTimer = setTimeout(() => {
    lintDebounceTimer = null;
    const controller = new AbortController();
    lintInflightController = controller;
    postLint(payload, { signal: controller.signal })
      .then((res) => {
        if (token !== lintRequestToken) {
          return;
        }
        lintReport.value = res.data;
        lintError.value = null;
      })
      .catch((err: unknown) => {
        if (token !== lintRequestToken) {
          return;
        }
        // AbortError is the normal cancel path — stay quiet.
        if (err instanceof DOMException && err.name === "AbortError") {
          return;
        }
        if (err instanceof ApiError) {
          lintError.value = `Lint check unavailable (HTTP ${err.status}).`;
        } else if (err instanceof Error) {
          lintError.value = `Lint check unavailable: ${err.message}`;
        } else {
          lintError.value = "Lint check unavailable.";
        }
        lintReport.value = null;
      })
      .finally(() => {
        if (token === lintRequestToken) {
          lintInflightController = null;
        }
      });
  }, DEBOUNCE_MS);
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

  const disposeAutoRegen = effect(() => {
    // Re-subscribe on every form change. We deliberately do NOT subscribe
    // to `rows` here — if rows are edited, the dirty tracker takes over.
    const f = form.value;
    const r = rows.value;
    if (!isGenerateEligible(f)) {
      cancelPendingRegen();
      return;
    }
    // If the user has edits in flight, defer to the dirty dialog flow.
    if (r.some((row) => row.edited)) {
      cancelPendingRegen();
      return;
    }
    // First-time generation has no baseline → fingerprint comparison
    // would skip; fall through and let scheduleRegen() run.
    if (lastGeneratedFingerprint !== null) {
      const fp = fingerprint(f);
      if (fp === lastGeneratedFingerprint && r.length > 0) {
        // Nothing changed since the last generate; idle.
        cancelPendingRegen();
        return;
      }
    }
    cancelPendingRegen();
    regenDebounceTimer = setTimeout(() => {
      regenDebounceTimer = null;
      // Re-check eligibility at fire time — form may have changed during
      // the debounce window (e.g. user cleared the airline).
      const latest = form.value;
      const latestRows = rows.value;
      if (!isGenerateEligible(latest)) {
        return;
      }
      if (latestRows.some((row) => row.edited)) {
        return;
      }
      regenerateRows();
    }, AUTO_REGEN_DEBOUNCE_MS);
  });

  const disposeAutoLint = effect(() => {
    // Subscribe to rows + the form. Touching `form.value` here means any
    // form mutation re-runs this effect; we re-snapshot inside buildLintPayload.
    const r = rows.value;
    void form.value;
    if (r.length === 0) {
      cancelInflightLint();
      lintReport.value = null;
      lintError.value = null;
      return;
    }
    const payload = buildLintPayload();
    if (payload === null) {
      // No airline picked yet — server would 422 on form validation.
      cancelInflightLint();
      lintReport.value = null;
      lintError.value = null;
      return;
    }
    scheduleLint(payload);
  });

  return () => {
    disposeDirty();
    disposeAutoRegen();
    disposeAutoLint();
    cancelPendingRegen();
    cancelInflightLint();
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
  // Suppress the unused-airlineStats warning — kept in the import surface
  // for future lint context wiring that may need it on the client side.
  void airlineStats;
}

/**
 * Reset the dirty tracker — called by handleDiscard and after a successful
 * commit so the next session starts with no baseline.
 */
export function resetLifecycleState(): void {
  lastGeneratedFingerprint = null;
  formStaleSinceGenerate.value = false;
  dirtyDialogOpen.value = false;
  cancelPendingRegen();
  cancelInflightLint();
}
