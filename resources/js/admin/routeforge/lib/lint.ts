/**
 * Client-side lint runner.
 *
 * Mirrors the 12 PHP rule classes under app/Services/RouteForge/Rules/
 * exactly: same IDs (L1, L2, L2b, L3, L4, L5, L6, L7, L8, L9, L10, L11),
 * same severities, same row_index attachment shape, same {rule, severity,
 * message, row_index, details} wire envelope. Translation messages here
 * are English fallbacks; the t() helper (task 8.3) will swap them once the
 * `filament.routeforge.lint.*` translation keys land in Section 8.
 *
 * Rules that PHP cannot replicate client-side:
 *   - L5 (existing DB duplicate): client-side can use cached duplicates from
 *     /admin/route-forge/api/check-duplicates, but the authoritative check
 *     is server-side at commit time. Client implementation here consumes a
 *     pre-fetched `existing_idents` set; absent that, L5 yields zero issues
 *     and we rely on the server to surface collisions.
 *
 * The runner is intentionally a single pure function with no signal/store
 * access — generator.ts and the live-lint debouncer pass in everything.
 */

import type { FlightTypeCode, LintIssue, LintReport, Row, SubfleetSummary } from "../state/types";
import { t } from "./i18n";

/**
 * Frozen runtime config drawn from window.routeforgeConfig.config plus the
 * caller's chosen flight_type / event / airline-stats snapshot. Mirrors the
 * PHP LintContext shape one-to-one.
 */
export type LintContext = {
  rows: Row[];
  selected_subfleets: SubfleetSummary[];
  flight_type: FlightTypeCode | null;
  event: { id: number; start_date: string; end_date: string } | null;
  bundle_start_date: string | null;
  bundle_end_date: string | null;
  airline_stats: {
    existing_active_flights_count: number;
    hub_airports: string[];
  };
  /**
   * Lookup of strict-key string → existing flight ident. Populated from
   * /admin/route-forge/api/check-duplicates so L5 can run client-side.
   * Empty → L5 is a no-op client-side.
   */
  existing_duplicates?: Record<string, { existing_flight_id: string; ident: string }>;
  /** Caps; default to the v1 spec values when window config is absent. */
  mesh_warn_count?: number;
  mesh_max_count?: number;
};

const DEFAULT_WARN_COUNT = 50;
const DEFAULT_MAX_COUNT = 100;

/**
 * Run every rule, bucket by severity, return the LintReport wire shape.
 */
export function runLint(ctx: LintContext): LintReport {
  const issues: LintIssue[] = [];

  issues.push(...l1AircraftCapacity(ctx));
  issues.push(...l2RangeMismatch(ctx));
  issues.push(...l2bTypeMismatch(ctx));
  issues.push(...l3EmptySubfleets(ctx));
  issues.push(...l4DuplicateFlightNumbersInBatch(ctx));
  issues.push(...l5ExistingDuplicate(ctx));
  issues.push(...l6OriginEqualsDestination(ctx));
  issues.push(...l7SubfleetsHaveNoFares(ctx));
  issues.push(...l8EventDatesOutsideWindow(ctx));
  issues.push(...l9BatchOver50(ctx));
  issues.push(...l10BatchOver100(ctx));
  issues.push(...l11AirportTimezoneMissing(ctx));

  return bucket(issues);
}

export function canProceed(report: LintReport): boolean {
  return report.errors.length === 0;
}

// ──────────────────────────────────────────────────────────────────────────
// Rules — keep in lockstep with app/Services/RouteForge/Rules/L*.php
// ──────────────────────────────────────────────────────────────────────────

function l1AircraftCapacity(ctx: LintContext): LintIssue[] {
  const rowCount = ctx.rows.length;
  if (rowCount === 0) {
    return [];
  }
  const selected = ctx.selected_subfleets.reduce((acc, s) => acc + s.aircraft_count, 0);
  const threshold = Math.floor(rowCount / 2);
  if (selected >= threshold) {
    return [];
  }
  return [
    issue("L1", "warning", t("lint.l1_capacity", { selected, count: rowCount, threshold }), null, {
      selected_aircraft_count: selected,
      row_count: rowCount,
      threshold,
    }),
  ];
}

function l2RangeMismatch(ctx: LintContext): LintIssue[] {
  // Unrestricted subfleet (max_range_nm null) covers any distance.
  const hasUnrestricted = ctx.selected_subfleets.some((s) => s.max_range_nm === null);
  if (hasUnrestricted) {
    return [];
  }
  const maxRange = maxOrNull(
    ctx.selected_subfleets.map((s) => s.max_range_nm).filter((v): v is number => v !== null),
  );
  if (maxRange === null) {
    // No subfleets selected — L3 covers; nothing to do here.
    return [];
  }
  const issues: LintIssue[] = [];
  for (const r of ctx.rows) {
    if (r.distance_nm <= maxRange) {
      continue;
    }
    issues.push(
      issue(
        "L2",
        "warning",
        t("lint.l2_range_mismatch", {
          distance: Math.round(r.distance_nm),
          range: maxRange,
        }),
        r.index,
        {
          distance_nm: r.distance_nm,
          max_subfleet_range: maxRange,
          incompatible_count: ctx.selected_subfleets.length,
        },
      ),
    );
  }
  return issues;
}

function l2bTypeMismatch(ctx: LintContext): LintIssue[] {
  const ft = ctx.flight_type;
  if (ft === null) {
    return [];
  }
  if (ctx.selected_subfleets.length === 0) {
    return [];
  }
  const compatible = ctx.selected_subfleets.some(
    (s) => s.route_types === null || s.route_types.includes(ft),
  );
  if (compatible) {
    return [];
  }
  // Batch-wide trigger but per-row attachment per spec.
  return ctx.rows.map((r) =>
    issue("L2b", "warning", t("lint.l2b_type_mismatch", { type: ft }), r.index, {
      flight_type: ft,
    }),
  );
}

function l3EmptySubfleets(ctx: LintContext): LintIssue[] {
  if (ctx.selected_subfleets.length > 0) {
    return [];
  }
  return [issue("L3", "warning", t("lint.l3_empty_subfleets"), null, {})];
}

function l4DuplicateFlightNumbersInBatch(ctx: LintContext): LintIssue[] {
  const seen = new Map<string, number>();
  const issues: LintIssue[] = [];
  for (const r of ctx.rows) {
    const key = strictKey(r);
    const firstIndex = seen.get(key);
    if (firstIndex === undefined) {
      seen.set(key, r.index);
      continue;
    }
    issues.push(
      issue(
        "L4",
        "error",
        t("lint.l4_duplicate_in_batch", {
          flight_number: r.flight_number,
          first: firstIndex,
          second: r.index,
        }),
        r.index,
        {
          flight_number: r.flight_number,
          airline_id: r.airline_id,
          route_code: r.route_code,
          route_leg: r.route_leg,
          first_row_index: firstIndex,
          duplicate_row_index: r.index,
        },
      ),
    );
  }
  return issues;
}

function l5ExistingDuplicate(ctx: LintContext): LintIssue[] {
  const idx = ctx.existing_duplicates;
  if (idx === undefined || Object.keys(idx).length === 0) {
    return [];
  }
  const issues: LintIssue[] = [];
  for (const r of ctx.rows) {
    const hit = idx[strictKey(r)];
    if (hit === undefined) {
      continue;
    }
    issues.push(
      issue(
        "L5",
        "warning",
        t("lint.l5_existing_duplicate", {
          flight_number: r.flight_number,
          ident: hit.ident,
        }),
        r.index,
        {
          existing_flight_id: hit.existing_flight_id,
          flight_number: r.flight_number,
          airline_id: r.airline_id,
        },
      ),
    );
  }
  return issues;
}

function l6OriginEqualsDestination(ctx: LintContext): LintIssue[] {
  const issues: LintIssue[] = [];
  for (const r of ctx.rows) {
    if (r.dpt_airport_id !== r.arr_airport_id) {
      continue;
    }
    issues.push(
      issue(
        "L6",
        "error",
        t("lint.l6_origin_equals_dest", { airport: r.dpt_airport_id }),
        r.index,
        { airport: r.dpt_airport_id },
      ),
    );
  }
  return issues;
}

function l7SubfleetsHaveNoFares(_ctx: LintContext): LintIssue[] {
  // Client-side fallback: SubfleetSummary doesn't carry a `fares` count in
  // v1 (the /subfleets endpoint doesn't expose it). Server-side L7 is
  // authoritative; client emits nothing here. UI will surface the warning
  // from the /lint endpoint response when the user clicks Run Lint.
  return [];
}

function l8EventDatesOutsideWindow(ctx: LintContext): LintIssue[] {
  if (ctx.event === null) {
    return [];
  }
  if (ctx.bundle_start_date === null && ctx.bundle_end_date === null) {
    return [];
  }
  const eventStart = parseISODate(ctx.event.start_date);
  const eventEnd = parseISODate(ctx.event.end_date);
  const bundleStart = ctx.bundle_start_date ? parseISODate(ctx.bundle_start_date) : null;
  const bundleEnd = ctx.bundle_end_date ? parseISODate(ctx.bundle_end_date) : null;

  const startsBeforeEventEnds = bundleStart === null || bundleStart <= eventEnd;
  const endsAfterEventStarts = bundleEnd === null || bundleEnd >= eventStart;
  if (startsBeforeEventEnds && endsAfterEventStarts) {
    return [];
  }
  return [
    issue(
      "L8",
      "warning",
      t("lint.l8_event_outside", {
        start: ctx.event.start_date,
        end: ctx.event.end_date,
      }),
      null,
      {
        event_id: ctx.event.id,
        event_start_date: ctx.event.start_date,
        event_end_date: ctx.event.end_date,
        bundle_start_date: ctx.bundle_start_date,
        bundle_end_date: ctx.bundle_end_date,
      },
    ),
  ];
}

function l9BatchOver50(ctx: LintContext): LintIssue[] {
  const threshold = ctx.mesh_warn_count ?? DEFAULT_WARN_COUNT;
  const count = ctx.rows.length;
  if (count <= threshold) {
    return [];
  }
  return [
    issue("L9", "warning", t("lint.l9_batch_over_50", { count, threshold }), null, {
      row_count: count,
      threshold,
    }),
  ];
}

function l10BatchOver100(ctx: LintContext): LintIssue[] {
  const cap = ctx.mesh_max_count ?? DEFAULT_MAX_COUNT;
  const count = ctx.rows.length;
  if (count <= cap) {
    return [];
  }
  return [
    issue("L10", "error", t("lint.l10_batch_over_100", { count, cap }), null, {
      row_count: count,
      cap,
    }),
  ];
}

function l11AirportTimezoneMissing(ctx: LintContext): LintIssue[] {
  const issues: LintIssue[] = [];
  for (const r of ctx.rows) {
    if (r.dpt_timezone !== null && r.arr_timezone !== null) {
      continue;
    }
    const missing: string[] = [];
    if (r.dpt_timezone === null) {
      missing.push(r.dpt_airport_id || "origin");
    }
    if (r.arr_timezone === null) {
      missing.push(r.arr_airport_id || "destination");
    }
    issues.push(
      issue(
        "L11",
        "warning",
        t("lint.l11_timezone_missing", { airports: missing.join(", ") }),
        r.index,
        { missing_timezone_airports: missing },
      ),
    );
  }
  return issues;
}

// ──────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────

function issue(
  rule: string,
  severity: LintIssue["severity"],
  message: string,
  rowIndex: number | null,
  details: Record<string, unknown>,
): LintIssue {
  return { rule, severity, message, row_index: rowIndex, details };
}

function bucket(issues: LintIssue[]): LintReport {
  const report: LintReport = { errors: [], warnings: [], info: [] };
  for (const i of issues) {
    if (i.severity === "error") {
      report.errors.push(i);
    } else if (i.severity === "warning") {
      report.warnings.push(i);
    } else {
      report.info.push(i);
    }
  }
  return report;
}

/**
 * Strict-key normalization MUST match PHP's:
 * L4DuplicateFlightNumbersInBatch::dupKey() / L5ExistingDuplicate::dupKey().
 * NULL ≡ '' ≡ 0 collapse to a single sentinel so the wire shape and the
 * client treat "absent route_code/route_leg" identically.
 */
export function strictKey(
  r: Pick<Row, "airline_id" | "flight_number" | "route_code" | "route_leg">,
): string {
  return [
    String(r.airline_id ?? ""),
    String(r.flight_number ?? ""),
    normalize(r.route_code),
    normalize(r.route_leg),
  ].join("|");
}

function normalize(v: string | number | null | undefined): string {
  if (v === null || v === undefined || v === "" || v === 0 || v === "0") {
    return "∅";
  }
  return String(v);
}

function maxOrNull(values: number[]): number | null {
  if (values.length === 0) {
    return null;
  }
  let m = values[0] as number;
  for (let i = 1; i < values.length; i++) {
    const v = values[i] as number;
    if (v > m) {
      m = v;
    }
  }
  return m;
}

/**
 * Parse YYYY-MM-DD as a UTC midnight Date. Avoids timezone drift inherent to
 * `new Date("YYYY-MM-DD")` which is parsed as UTC anyway in modern engines
 * but explicit construction documents the intent and shields against future
 * spec changes.
 */
function parseISODate(s: string): Date {
  const [y, m, d] = s.split("-").map((x) => Number.parseInt(x, 10));
  return new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1));
}
