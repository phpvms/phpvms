/**
 * Draft persistence (Decision 4).
 *
 * Single resumable draft in `localStorage` under DRAFT_KEY. Writes are
 * debounced to 300ms after the last change so rapid typing in the form
 * doesn't hammer storage. Reads run once on page load to drive the resume
 * banner. Writes are best-effort: a full / disabled localStorage (private
 * mode, quota exceeded) is swallowed silently — the user gets no draft but
 * the tool stays usable.
 *
 * Versioning: DRAFT_VERSION bump invalidates older envelopes on load. v1
 * here; multi-draft (v2) would change DRAFT_KEY shape too.
 *
 * The flushDraft() helper is for "user is about to commit" — force the
 * pending debounce to write now so a refresh between commit and the
 * resulting redirect doesn't lose the draft if the commit fails.
 */

import {
  DRAFT_KEY,
  DRAFT_STALE_DAYS,
  DRAFT_VERSION,
  type AirlineStats,
  type AirportSummary,
  type DraftEnvelope,
  type Form,
  type Icao,
  type Row,
  type SubfleetSummary,
} from "./types";

const DEBOUNCE_MS = 300;

const STALE_MS = DRAFT_STALE_DAYS * 24 * 60 * 60 * 1000;

let pending: DraftPayload | null = null;
let timer: ReturnType<typeof setTimeout> | null = null;

/** Subset the caller assembles per save (everything except the envelope wrapping). */
export type DraftPayload = {
  form: Form;
  rows: Row[];
  airports: Record<Icao, AirportSummary>;
  subfleets: Record<number, SubfleetSummary>;
  airline_stats: AirlineStats | null;
};

export type LoadResult = {
  envelope: DraftEnvelope;
  /** True if saved_at is older than DRAFT_STALE_DAYS. UI nudges user to start fresh. */
  is_stale: boolean;
};

/**
 * Schedule a write 300ms after this call (resets on each subsequent call).
 * Latest payload wins; in-flight earlier payloads are dropped.
 */
export function saveDraft(payload: DraftPayload): void {
  pending = payload;
  if (timer !== null) {
    clearTimeout(timer);
  }
  timer = setTimeout(() => {
    timer = null;
    if (pending !== null) {
      writeNow(pending);
      pending = null;
    }
  }, DEBOUNCE_MS);
}

/**
 * Force any pending save to run NOW. Returns once the write completes.
 * Used at commit time so the latest draft is on disk if the commit fails.
 */
export function flushDraft(): void {
  if (timer !== null) {
    clearTimeout(timer);
    timer = null;
  }
  if (pending !== null) {
    writeNow(pending);
    pending = null;
  }
}

/**
 * Read the persisted draft, validate shape + version, return with staleness
 * flag. Returns null if no draft exists, parse failed, shape is wrong, or
 * the version is older than the current code.
 */
export function loadDraft(): LoadResult | null {
  let raw: string | null;
  try {
    raw = localStorage.getItem(DRAFT_KEY);
  } catch {
    return null;
  }
  if (raw === null || raw === "") {
    return null;
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch {
    return null;
  }

  if (!isEnvelope(parsed)) {
    return null;
  }
  if (parsed.version !== DRAFT_VERSION) {
    return null;
  }

  const savedAt = Date.parse(parsed.saved_at);
  const is_stale = Number.isFinite(savedAt) && Date.now() - savedAt > STALE_MS;

  return { envelope: parsed, is_stale };
}

/**
 * Wipe the draft. Called after a successful commit and on the "Discard draft"
 * user path. Also cancels any pending debounced write to prevent a re-save
 * after clear.
 */
export function clearDraft(): void {
  if (timer !== null) {
    clearTimeout(timer);
    timer = null;
  }
  pending = null;
  try {
    localStorage.removeItem(DRAFT_KEY);
  } catch {
    // noop — see module docblock
  }
}

// ─── Internals ────────────────────────────────────────────────────────────

function writeNow(payload: DraftPayload): void {
  const envelope: DraftEnvelope = {
    version: DRAFT_VERSION,
    saved_at: new Date().toISOString(),
    ...payload,
  };
  try {
    localStorage.setItem(DRAFT_KEY, JSON.stringify(envelope));
  } catch {
    // Storage may be full or disabled; the user loses the draft but the
    // session continues. Surfacing this to the UI is a v2 polish item.
  }
}

function isEnvelope(x: unknown): x is DraftEnvelope {
  if (typeof x !== "object" || x === null) {
    return false;
  }
  const o = x as Record<string, unknown>;
  return (
    typeof o.version === "number" &&
    typeof o.saved_at === "string" &&
    typeof o.form === "object" &&
    o.form !== null &&
    Array.isArray(o.rows) &&
    typeof o.airports === "object" &&
    o.airports !== null &&
    typeof o.subfleets === "object" &&
    o.subfleets !== null
  );
}
