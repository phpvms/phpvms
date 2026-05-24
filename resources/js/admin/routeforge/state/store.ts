/**
 * RouteForge client store — signal-based reactive state.
 *
 * Built on @preact/signals (already a runtime dependency). The store exposes
 * a flat set of `signal`s for mutable state and `computed`s for derived
 * values; components import the signals directly and Preact subscribes by
 * accessing `.value` inside render.
 *
 * State partition:
 *   form               full form (drives generator, persisted to draft)
 *   rows               generated/edited rows (persisted)
 *   lintReport         most recent /lint response (or null pre-generation)
 *   isDirty            true once the user edits a row OR changes a generator-
 *                      affecting form field after rows exist
 *   draftLoaded        true after the resume-banner flow finishes (load or skip)
 *   lastSavedAt        clock display for the "Last saved Xs ago" footer
 *   airportCache /     populated from /preview-airports and /subfleets;
 *   subfleetCache      mirrored into the draft envelope so resume is offline
 *   airlineStats       null until airline picked; cached per airline_id
 *
 * No I/O happens in this module. Components call api.ts to fetch, then
 * write signals here; persistence.ts subscribes to relevant signals and
 * debounces writes to localStorage.
 */

import { computed, signal } from "@preact/signals";
import type {
  AirlineStats,
  AirportSummary,
  Form,
  Icao,
  LintReport,
  Row,
  SubfleetSummary,
} from "./types";

// ─── Defaults ─────────────────────────────────────────────────────────────

/**
 * Empty-state form. Used on first page load and on the "Discard draft" path.
 * Values match the spec's default-when-unset behavior; the FormPanel may
 * mutate any of these via signal writes.
 */
export function defaultForm(): Form {
  return {
    airline_id: null,
    topology: "hub_spokes",
    origins: [],
    destinations: [],
    mode: "cartesian",
    create_returns: false,
    subfleet_ids: [],
    flight_type: null,
    event_id: null,
    // Default: every day. 0b1111111 = 127.
    days_mask: 127,
    time_strategy: {
      kind: "fixed",
      base_time: "08:00",
      jitter: { enabled: false, minutes: 0, seed: 1 },
    },
    flight_number_strategy: { kind: "sequential", base: 100 },
    route_preset: "custom",
    frequency_preset: "custom",
    bundle: {
      existing_bundle_id: null,
      name: "",
      description: "",
      enabled: false,
      start_date: null,
      end_date: null,
      fare_multiplier: "",
      activate_on_save: false,
    },
  };
}

// ─── Mutable signals ──────────────────────────────────────────────────────

export const form = signal<Form>(defaultForm());
export const rows = signal<Row[]>([]);
export const lintReport = signal<LintReport | null>(null);
export const isDirty = signal<boolean>(false);
export const draftLoaded = signal<boolean>(false);
export const lastSavedAt = signal<Date | null>(null);

export const airportCache = signal<Record<Icao, AirportSummary>>({});
export const subfleetCache = signal<Record<number, SubfleetSummary>>({});
export const airlineStats = signal<AirlineStats | null>(null);

// ─── Computed views ───────────────────────────────────────────────────────

export const rowCount = computed<number>(() => rows.value.length);

export const selectedRowCount = computed<number>(() =>
  rows.value.reduce((n, r) => (r.enabled ? n + 1 : n), 0),
);

/**
 * UI commit-button gate. Requires:
 *   - at least one row
 *   - a lint pass has run (lintReport !== null) so the user isn't committing
 *     a payload that hasn't been validated client-side
 *   - no errors in the most recent lint
 *
 * The server re-runs lint at commit time, so this gate is UX only. Falsely
 * letting a commit through here just produces a 422 from the endpoint.
 */
export const canCommit = computed<boolean>(() => {
  if (rowCount.value === 0) {
    return false;
  }
  const report = lintReport.value;
  if (report === null) {
    return false;
  }
  return report.errors.length === 0;
});

/**
 * Reset to empty state. Called from the "Discard draft" path and after a
 * successful commit (alongside clearDraft from persistence.ts).
 */
export function resetStore(): void {
  form.value = defaultForm();
  rows.value = [];
  lintReport.value = null;
  isDirty.value = false;
  draftLoaded.value = false;
  lastSavedAt.value = null;
  airportCache.value = {};
  subfleetCache.value = {};
  airlineStats.value = null;
}
