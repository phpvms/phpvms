/**
 * RouteForge cross-cutting type definitions.
 *
 * Every shape that crosses a module boundary or hits the wire lives here so
 * one file is the source of truth and PHP↔TS contract drift surfaces at
 * `tsc --noEmit` rather than at runtime. Wire shapes mirror the PHP DTOs
 * exactly:
 *
 *   LintIssue   ↔ App\Services\RouteForge\LintIssue::toArray()
 *   LintReport  ↔ App\Services\RouteForge\LintReport::toArray()
 *   CommitResp  ↔ App\Services\RouteForge\CommitResult::toArray()
 *   AirportSummary, SubfleetSummary, AirlineStats, DuplicateMatch ↔
 *                 corresponding RouteForge Resources under
 *                 app/Http/Resources/RouteForge.
 *
 * Discriminated unions (TimeStrategy, FlightNumberStrategy) carry a `kind`
 * field so exhaustive `switch` statements in generator.ts and timeStrategy.ts
 * stay type-safe under `strict: true`.
 */

// ─── Primitive aliases ─────────────────────────────────────────────────────

/** ICAO airport identifier; 4 uppercase chars when valid. PK of `airports`. */
export type Icao = string;

/** Backing value of App\Enums\FlightType (24 IATA service-type letters). */
export type FlightTypeCode =
  | "J"
  | "F"
  | "C"
  | "A"
  | "E"
  | "G"
  | "H"
  | "I"
  | "K"
  | "M"
  | "O"
  | "P"
  | "T"
  | "W"
  | "X"
  | "S"
  | "B"
  | "Q"
  | "R"
  | "L"
  | "D"
  | "N"
  | "Y"
  | "Z";

/** 7-bit day-of-week mask. Mon = 1<<0 ... Sun = 1<<6, matching phpvms convention. */
export type DaysMask = number;

// ─── Topology + presets ────────────────────────────────────────────────────

/**
 * User-facing topology pick. Drives form UI labels and origin/destination
 * multiplicity rules; the generator collapses these into `mode` + `create_returns`.
 */
export type Topology =
  | "hub_spokes" // 1 origin → N destinations
  | "spokes_hub" // N origins → 1 destination
  | "hub_and_spokes" // hub_spokes with auto-return legs
  | "mesh" // N origins × M destinations cartesian
  | "chain"; // origins[0]→origins[1]→…→origins[N-1]

export type RoutePreset =
  | "regional_spoke"
  | "long_haul_daily"
  | "weekend_leisure"
  | "cargo_night"
  | "training"
  | "positioning"
  | "custom";

export type FrequencyPreset =
  | "daily"
  | "weekdays"
  | "weekends"
  | "three_weekly"
  | "tue_thu_sat"
  | "nightly_weekdays"
  | "training_always"
  | "custom";

// ─── Time-strategy tagged union ────────────────────────────────────────────

/**
 * Seeded jitter applied on top of any time strategy. Seeded so identical
 * inputs produce identical jitter offsets — generator output stays deterministic
 * across regenerations, which the lint catalog assumes.
 */
export type JitterConfig = {
  enabled: boolean;
  /** Maximum absolute offset in minutes; rows shift by uniformly random value in [-N, +N]. */
  minutes: number;
  /** PRNG seed; combined with row index to produce per-row offset. */
  seed: number;
};

export type TimeStrategy =
  | { kind: "fixed"; base_time: string; jitter: JitterConfig }
  | {
      kind: "spread";
      base_time: string;
      /** Per-origin gap between successive departures. */
      interval_minutes: number;
      jitter: JitterConfig;
    }
  | {
      kind: "banked";
      base_time: string;
      /** Number of departure banks per origin. Rows distribute round-robin across banks. */
      bank_count: number;
      /** Minutes between successive banks. */
      bank_spacing_minutes: number;
      jitter: JitterConfig;
    }
  | {
      kind: "redeye";
      base_time: string;
      /** Total window minutes; rows distribute evenly inside it. */
      window_minutes: number;
      jitter: JitterConfig;
    };

// ─── Flight-number strategy tagged union ───────────────────────────────────

/**
 * Strategy assignments are positional: the generator emits rows in
 * `[outbound, return, outbound, return, ...]` order when `create_returns` is
 * true, and the assigner relies on that ordering for the even/odd strategies.
 */
export type FlightNumberStrategy =
  | { kind: "sequential"; base: number }
  | { kind: "even_odd_by_direction"; base: number }
  | { kind: "even_outbound_only"; base: number }
  | { kind: "manual" };

// ─── Bundle config ─────────────────────────────────────────────────────────

/**
 * Mirrors the `bundle` envelope object on the /lint and /commit payloads.
 * `fare_multiplier` is validated server-side against `/^[+-]?\d+(\.\d+)?%$/`
 * (Decision 9); empty string is "no multiplier".
 *
 * Dual-mode: when `existing_bundle_id` is non-null, the batch attaches to
 * that pre-existing FlightBundle row and the server IGNORES name / description
 * / enabled / start_date / end_date — the existing bundle's values stay
 * authoritative. `fare_multiplier` is per-batch (Decision 9) so it still
 * applies to the new flights even when an existing bundle is selected,
 * though the v1 UI hides the input in that mode (use "create new" when fare
 * adjustment is needed).
 */
export type BundleConfig = {
  /** Non-null = attach new flights to this existing bundle; null = create new. */
  existing_bundle_id: number | null;
  name: string;
  description: string;
  enabled: boolean;
  start_date: string | null; // YYYY-MM-DD
  end_date: string | null;
  fare_multiplier: string;
  /** UI sugar: pinned-above-Create checkbox that mirrors `enabled` at commit. */
  activate_on_save: boolean;
};

/**
 * Subset of a FlightBundle row served by the Filament page at mount time
 * (window.routeforgeConfig.bundles). Drives the existing-bundle picker.
 * Dates are ISO strings (YYYY-MM-DD) so the read-only display can render
 * them without parsing.
 */
export type BundleSummary = {
  id: number;
  name: string;
  description: string | null;
  enabled: boolean;
  start_date: string | null;
  end_date: string | null;
};

// ─── Form state ────────────────────────────────────────────────────────────

/**
 * The full client form. `mode` is derived from `topology` by the generator
 * (cartesian for hub/spokes/mesh; chain for chain) but stored explicitly so
 * the unified internal generator path stays trivial.
 */
export type Form = {
  airline_id: number | null;
  topology: Topology;
  origins: Icao[];
  destinations: Icao[];
  mode: "cartesian" | "chain";
  create_returns: boolean;
  subfleet_ids: number[];
  flight_type: FlightTypeCode | null;
  event_id: number | null;
  days_mask: DaysMask;
  time_strategy: TimeStrategy;
  flight_number_strategy: FlightNumberStrategy;
  route_preset: RoutePreset;
  frequency_preset: FrequencyPreset;
  bundle: BundleConfig;
};

// ─── Row shape (generator output + commit payload row) ─────────────────────

/**
 * Per-row leg direction. Drives the even/odd flight-number strategies and
 * lets the UI render outbound vs return distinctly. Generator emits rows
 * interleaved `[out, ret, out, ret, ...]` when create_returns is true.
 */
export type LegDirection = "outbound" | "return";

export type Row = {
  /** Zero-based, stable within a single generation. */
  index: number;
  /** Pair group for this row (0, 1, 2, ...). Outbound + return share the pair_index. */
  pair_index: number;
  direction: LegDirection;

  airline_id: number;
  flight_number: number;
  route_code: string | null;
  route_leg: string | null;

  dpt_airport_id: Icao;
  arr_airport_id: Icao;

  /** IANA TZ from /preview-airports decoration; null surfaces L11 lint. */
  dpt_timezone: string | null;
  arr_timezone: string | null;

  /** Origin-local HH:MM. */
  dpt_time: string;
  /** Destination-local HH:MM. */
  arr_time: string;
  /** +N calendar days from departure local date. 0 for same-day arrivals. */
  arr_day_shift: number;

  distance_nm: number;
  /** Block time in minutes (haversine / cruise + climb/descent buffer). */
  flight_time: number;

  days_mask: DaysMask;
  flight_type: FlightTypeCode | null;
  enabled: boolean;

  /** Client-only marker: true once the user has edited any cell. Stripped before commit. */
  edited: boolean;
};

/** Commit/lint payload row shape — `edited` and other UI-only fields are stripped. */
export type PayloadRow = Omit<Row, "edited">;

// ─── Lint wire shapes (mirror PHP LintIssue / LintReport) ──────────────────

export type LintSeverity = "error" | "warning" | "info";

/** Stable rule identifiers — server-side rules emit the same set. */
export type LintRuleId =
  | "L1"
  | "L2"
  | "L2b"
  | "L3"
  | "L4"
  | "L5"
  | "L6"
  | "L7"
  | "L8"
  | "L9"
  | "L10"
  | "L11";

export type LintIssue = {
  rule: LintRuleId | string;
  severity: LintSeverity;
  message: string;
  row_index: number | null;
  details: Record<string, unknown>;
};

export type LintReport = {
  errors: LintIssue[];
  warnings: LintIssue[];
  info: LintIssue[];
};

// ─── Airport / Subfleet / Airline summaries (returned by admin endpoints) ──

/**
 * /admin/route-forge/api/preview-airports response item. Extends the public
 * AirportResource with the two RouteForge-only decorations (only present
 * when the request supplied `near` / `max_range_nm`).
 */
export type AirportSummary = {
  id: Icao;
  icao: string;
  iata: string | null;
  name: string;
  country: string | null;
  region: string | null;
  location: string | null;
  lat: number | null;
  lon: number | null;
  timezone: string | null;
  hub: boolean;
  elevation: number | null;
  distance_from_origin_nm?: number;
  in_subfleet_range?: boolean;
};

/**
 * /admin/route-forge/api/subfleets response item. route_types = null means
 * unrestricted (compatible with any flight_type for L2b purposes).
 */
export type SubfleetSummary = {
  id: number;
  name: string;
  type: string;
  cruise_speed: number | null;
  max_range_nm: number | null;
  route_types: FlightTypeCode[] | null;
  aircraft_count: number;
};

export type AirlineStats = {
  existing_active_flights_count: number;
  hub_airports: Icao[];
  home_airport: Icao | null;
};

export type AirlineSummary = {
  id: number;
  name: string;
  icao: string | null;
  iata: string | null;
};

// ─── Duplicate-check wire shapes ───────────────────────────────────────────

export type DuplicateMatch = {
  index: number;
  existing_flight_id: string;
  ident: string;
  /** Always 'flight_number' in v1. */
  conflict_field: string;
};

export type DuplicateCheckResponse = {
  duplicates: DuplicateMatch[];
};

// ─── Commit wire shapes ────────────────────────────────────────────────────

export type CommitResponse = {
  bundle_id: number;
  batch_id: string;
  created_count: number;
  flight_ids: string[];
  /** Reserved for `on_conflict: 'skip'`; always [] in v1. */
  skipped: unknown[];
};

export type LintPayload = {
  airline_id: number;
  event_id: number | null;
  subfleet_ids: number[];
  flight_type: FlightTypeCode | null;
  bundle: BundleConfig;
  rows: PayloadRow[];
};

export type CommitPayload = LintPayload & {
  on_conflict: "skip" | "abort";
};

// ─── window.routeforgeConfig (set by Filament Blade view) ──────────────────

export type RouteForgeRoutes = {
  preview_airports: string;
  subfleets: string;
  airline_stats: string;
  check_duplicates: string;
  lint: string;
  commit: string;
};

export type RouteForgeServerConfig = {
  cruise_speed_kt?: number;
  climb_descent_buffer?: number;
  turnaround_minutes?: number;
  mesh_warn_count?: number;
  mesh_max_count?: number;
  [k: string]: unknown;
};

export type WindowConfig = {
  csrf_token: string;
  locale: string;
  user: { id: number; name: string | null; can_commit: boolean };
  airlines: AirlineSummary[];
  bundles: BundleSummary[];
  routes: RouteForgeRoutes;
  config: RouteForgeServerConfig;
  translations: Record<string, unknown>;
};

declare global {
  interface Window {
    routeforgeConfig?: WindowConfig;
  }
}

// ─── Draft envelope (localStorage shape) ───────────────────────────────────

export const DRAFT_KEY = "routeforge:draft:v1";
export const DRAFT_VERSION = 1 as const;
export const DRAFT_STALE_DAYS = 30;

/**
 * One resumable draft. Includes caches so a resume doesn't immediately
 * re-fetch /preview-airports + /subfleets to render the form. Caches are
 * authoritative on resume; the user can re-fetch by changing the airline
 * selector (which clears the subfleet cache).
 */
export type DraftEnvelope = {
  version: typeof DRAFT_VERSION;
  /** ISO 8601 timestamp; used by the stale-draft check (DRAFT_STALE_DAYS). */
  saved_at: string;
  form: Form;
  rows: Row[];
  airports: Record<Icao, AirportSummary>;
  subfleets: Record<number, SubfleetSummary>;
  airline_stats: AirlineStats | null;
};
