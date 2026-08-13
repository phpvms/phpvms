/**
 * Draft resume prompt.
 *
 * Shown when loadDraft() returns a non-null envelope. Two actions:
 *
 *   - Resume:  hydrate the store from the envelope, flip draftLoaded.
 *              The persistence effect (registered in App.tsx after this flip)
 *              skips its first sync run so we don't immediately re-save what
 *              we just loaded.
 *
 *   - Discard: clearDraft() removes the localStorage entry and cancels any
 *              pending debounced write; resetStore() wipes the in-memory
 *              state (including draftLoaded, which we then flip back to true
 *              so the layout renders).
 *
 * Stale text (spec: "indicates the draft is over 30 days old") fires when
 * persistence.loadDraft() set `is_stale = true` (DRAFT_STALE_DAYS = 30).
 *
 * onResolve() lets App clear its local `resumeResult` state so the banner
 * unmounts cleanly after either action.
 */

import { t } from "../lib/i18n";
import type { LoadResult } from "../state/persistence";
import { clearDraft } from "../state/persistence";
import {
  airlineStats,
  airportCache,
  draftLoaded,
  form,
  lastSavedAt,
  resetStore,
  rows,
  subfleetCache,
} from "../state/store";

export type DraftResumeBannerProps = {
  result: LoadResult;
  onResolve: () => void;
};

export function DraftResumeBanner({ result, onResolve }: DraftResumeBannerProps) {
  const { envelope, is_stale } = result;
  const savedAt = new Date(envelope.saved_at);

  function handleResume(): void {
    // Hydrate signals BEFORE flipping draftLoaded so the persistence
    // effect (which arms on the flip) sees the resumed state on its
    // skipped first run.
    form.value = envelope.form;
    rows.value = envelope.rows;
    airportCache.value = envelope.airports;
    subfleetCache.value = envelope.subfleets;
    airlineStats.value = envelope.airline_stats;
    lastSavedAt.value = savedAt;
    draftLoaded.value = true;
    onResolve();
  }

  function handleDiscard(): void {
    // clearDraft first so any in-flight debounced write is cancelled
    // before resetStore mutations would otherwise re-arm one.
    clearDraft();
    resetStore();
    // resetStore() sets draftLoaded=false; flip back so the layout
    // renders. Persistence effect arms here with skipFirst, so the
    // empty defaults from resetStore() do NOT get persisted —
    // localStorage stays cleared.
    draftLoaded.value = true;
    onResolve();
  }

  return (
    <div
      role="status"
      class="mx-auto flex max-w-2xl flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
      <div class="space-y-2">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
          {t("banner.title")}
        </h2>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          {t("banner.saved_at", { date: savedAt.toLocaleString() })}
        </p>
        {is_stale && (
          <p class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
            {t("banner.stale_warning")}
          </p>
        )}
      </div>
      <div class="flex flex-wrap justify-end gap-2">
        <button
          type="button"
          class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
          onClick={handleDiscard}
        >
          {t("banner.discard")}
        </button>
        <button
          type="button"
          class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
          onClick={handleResume}
        >
          {t("banner.resume")}
        </button>
      </div>
    </div>
  );
}
