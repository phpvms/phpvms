/**
 * Typed fetch wrappers for the six /admin/route-forge/api/* endpoints.
 *
 * Reads CSRF token + endpoint URLs from window.routeforgeConfig (populated
 * by RouteForge::buildConfig() in the Filament Blade view). All POSTs send
 * the X-CSRF-TOKEN header to clear Laravel's web middleware; GETs go through
 * the same session cookie. Non-2xx responses throw `ApiError` carrying the
 * status + parsed body so callers can switch on 422 (Form Request / lint
 * failure) vs 403/401 (auth) vs everything else.
 *
 * The commit endpoint is special: it can respond 422 with the LintReport
 * envelope (when server-side lint catches errors). Callers should catch
 * ApiError, check status === 422, and read err.body as `LintReport` —
 * shape is identical to the /lint endpoint's success body.
 */

import type {
  AirlineStats,
  AirportSummary,
  CommitPayload,
  CommitResponse,
  DuplicateCheckResponse,
  LintPayload,
  LintReport,
  PayloadRow,
  SubfleetSummary,
  WindowConfig,
} from "../state/types";

/** Thrown for any non-2xx response. `body` is the parsed JSON (or null on parse fail). */
export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly body: unknown,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

// ─── Endpoint params ──────────────────────────────────────────────────────

export type PreviewAirportsParams = {
  /** Free-text typeahead. Maps to the `search` query param consumed by App\Queries\AirportSearchQueryV1. */
  search?: string;
  /**
   * 'prefix' = LIKE 'value%' (starts-with); 'substring' (default) = LIKE '%value%'.
   * RouteForge sends 'prefix' so typing "M" matches "MIA"/"MUM"/etc., not airports
   * whose name happens to contain an M.
   */
  searchMode?: "prefix" | "substring";
  near?: string;
  max_range_nm?: number;
  limit?: number;
  page?: number;
};

export type SubfleetsParams = {
  airline_id: number;
};

export type AirlineStatsParams = {
  airline_id: number;
};

export type CheckDuplicatesParams = {
  rows: Pick<
    PayloadRow,
    | "airline_id"
    | "flight_number"
    | "route_code"
    | "route_leg"
    | "dpt_airport_id"
    | "arr_airport_id"
  >[];
};

// ─── Endpoint responses (paginated airports follows Laravel default) ──────

export type PaginatedResponse<T> = {
  data: T[];
  links?: unknown;
  meta?: { current_page: number; last_page: number; per_page: number; total: number };
};

export type SingleResponse<T> = { data: T };

// ─── Endpoint wrappers ────────────────────────────────────────────────────

export async function getPreviewAirports(
  params: PreviewAirportsParams = {},
): Promise<PaginatedResponse<AirportSummary>> {
  const url = buildUrl(routes().preview_airports, params);
  return getJson<PaginatedResponse<AirportSummary>>(url);
}

export async function getSubfleets(
  params: SubfleetsParams,
): Promise<SingleResponse<SubfleetSummary[]> | { data: SubfleetSummary[] }> {
  const url = buildUrl(routes().subfleets, params);
  return getJson(url);
}

export async function getAirlineStats(
  params: AirlineStatsParams,
): Promise<SingleResponse<AirlineStats>> {
  const url = buildUrl(routes().airline_stats, params);
  return getJson<SingleResponse<AirlineStats>>(url);
}

export async function postCheckDuplicates(
  params: CheckDuplicatesParams,
): Promise<SingleResponse<DuplicateCheckResponse>> {
  return postJson<SingleResponse<DuplicateCheckResponse>>(routes().check_duplicates, params);
}

export async function postLint(payload: LintPayload): Promise<SingleResponse<LintReport>> {
  return postJson<SingleResponse<LintReport>>(routes().lint, payload);
}

/**
 * Commit can return 422 with a LintReport body (LintFailedException → 422 in
 * the controller). Callers MUST catch ApiError and inspect status.
 */
export async function postCommit(payload: CommitPayload): Promise<SingleResponse<CommitResponse>> {
  return postJson<SingleResponse<CommitResponse>>(routes().commit, payload);
}

// ─── Internal HTTP helpers ────────────────────────────────────────────────

async function getJson<T>(url: string): Promise<T> {
  const res = await fetch(url, {
    method: "GET",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });
  return handleResponse<T>(res, url);
}

async function postJson<T>(url: string, body: unknown): Promise<T> {
  const res = await fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": config().csrf_token,
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify(body),
  });
  return handleResponse<T>(res, url);
}

async function handleResponse<T>(res: Response, url: string): Promise<T> {
  let parsed: unknown = null;
  const text = await res.text();
  if (text !== "") {
    try {
      parsed = JSON.parse(text);
    } catch {
      parsed = text;
    }
  }
  if (!res.ok) {
    throw new ApiError(`${res.status} ${res.statusText} (${url})`, res.status, parsed);
  }
  return parsed as T;
}

function buildUrl(base: string, params: Record<string, unknown>): string {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === null) {
      continue;
    }
    qs.append(k, String(v));
  }
  const qsStr = qs.toString();
  return qsStr === "" ? base : `${base}?${qsStr}`;
}

/**
 * Read window.routeforgeConfig; throws clearly if the Filament page didn't
 * inject it (would mean the Blade view broke between deploy and load).
 */
function config(): WindowConfig {
  const cfg = window.routeforgeConfig;
  if (cfg === undefined) {
    throw new Error(
      "window.routeforgeConfig is not set; RouteForge page may have failed to render.",
    );
  }
  return cfg;
}

function routes(): WindowConfig["routes"] {
  return config().routes;
}
