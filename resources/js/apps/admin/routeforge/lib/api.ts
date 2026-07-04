/**
 * Typed fetch wrappers for the /admin/route-forge/api/* endpoints.
 *
 * Reads CSRF token + endpoint URLs from the hydrated boot envelope (see
 * `state/boot.ts`). The boot envelope is fetched once at SPA mount by
 * `main.tsx`; every subsequent POST in this file reads the CSRF token from
 * the in-memory store, not from `window.*`.
 *
 * Non-2xx responses throw `ApiError` carrying the status + parsed body so
 * callers can switch on 422 (Form Request / lint failure) vs 403/401 (auth)
 * vs everything else.
 *
 * The commit endpoint is special: it can respond 422 with the LintReport
 * envelope (when server-side lint catches errors). Callers should catch
 * ApiError, check status === 422, and read err.body as `LintReport` —
 * shape is identical to the /lint endpoint's success body.
 */

import { getBootOrThrow } from "../state/boot";
import type {
  AirlineStats,
  AirportSummary,
  BootEnvelope,
  BundleSummary,
  CommitPayload,
  CommitResponse,
  LintPayload,
  LintReport,
  RouteForgeRoutes,
  SubfleetSummary,
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

export type BundlesParams = {
  search?: string;
  page?: number;
  per_page?: number;
};

// ─── Endpoint responses (paginated airports follows Laravel default) ──────

export type PaginatedResponse<T> = {
  data: T[];
  links?: unknown;
  meta?: { current_page: number; last_page: number; per_page: number; total: number };
};

export type SingleResponse<T> = { data: T };

// ─── Boot fetch (special: read before store hydration) ────────────────────

/** Cached after first read; null until main.tsx asks for it. */
let cachedBootUrl: string | null = null;

/**
 * Read the `data-boot-url` attribute off `#routeforge-root` (rendered by the
 * Filament Blade view). Cached after first call so retries inside a single
 * page load skip the DOM walk.
 */
function readBootUrl(): string {
  if (cachedBootUrl !== null) {
    return cachedBootUrl;
  }
  const root = document.getElementById("routeforge-root");
  const url = root?.dataset.bootUrl;
  if (url === undefined || url === "") {
    throw new Error(
      "RouteForge mount point is missing the data-boot-url attribute; the Filament Blade view did not render correctly.",
    );
  }
  cachedBootUrl = url;
  return url;
}

/**
 * Fetch the boot envelope. Throws `ApiError` on non-2xx, or a plain `Error`
 * if the bootstrap URL itself is missing (broken Blade view).
 *
 * Callers MUST call `hydrateBoot()` with the resolved envelope before
 * touching any other helper in this module.
 */
export async function getBoot(): Promise<BootEnvelope> {
  const url = readBootUrl();
  const res = await fetch(url, {
    method: "GET",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  });
  const envelope = await handleResponse<{ data?: BootEnvelope } | BootEnvelope>(res, url);
  // The Laravel JsonResource wraps payloads under `data` by default.
  if (envelope !== null && typeof envelope === "object" && "data" in envelope) {
    return (envelope as { data: BootEnvelope }).data;
  }
  return envelope as BootEnvelope;
}

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

/**
 * Paginated + searchable feed of non-soft-deleted FlightBundles. Backs the
 * existing-bundle picker in BundleConfigSection. Server applies a case-
 * insensitive LIKE filter on `name` when `search` is set.
 */
export async function getBundles(
  params: BundlesParams = {},
): Promise<PaginatedResponse<BundleSummary>> {
  const url = buildUrl(routes().bundles, params);
  return getJson<PaginatedResponse<BundleSummary>>(url);
}

export async function postLint(
  payload: LintPayload,
  options: { signal?: AbortSignal } = {},
): Promise<SingleResponse<LintReport>> {
  return postJson<SingleResponse<LintReport>>(routes().lint, payload, options.signal);
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

async function postJson<T>(url: string, body: unknown, signal?: AbortSignal): Promise<T> {
  const res = await fetch(url, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": getBootOrThrow().csrf_token,
      "X-Requested-With": "XMLHttpRequest",
    },
    body: JSON.stringify(body),
    signal,
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

function routes(): RouteForgeRoutes {
  return getBootOrThrow().routes;
}
