/**
 * Right-pane: header (counts + stale banner), body (table or success card),
 * footer (Create Flights button), modals (DirtyWarningDialog +
 * LintReportDialog).
 *
 * The commit flow is a small tagged-union state machine. Order of operations
 * matches the design doc and the spec scenarios:
 *
 *   idle
 *     → user clicks "Create flights"
 *     → flushDraft so the in-flight typing is persisted in case the commit
 *       fails (Decision 4 / persistence.ts docblock)
 *     → inspect lintReport.value (already kept fresh by the auto-lint
 *       effect on 400ms debounce — no extra HTTP round-trip)
 *
 *   lintReport has errors or warnings  → lint_review (dialog)
 *   lintReport clean OR null           → doCommit() immediately
 *
 *   lint_review --(user clicks Proceed)--> doCommit()
 *   lint_review --(user clicks Cancel)---> idle
 *
 *   doCommit
 *     → POST /commit
 *     → 200: result stored, draft cleared, render <CommitSuccessRedirect/>
 *     → 422 with body.data: { errors/warnings/info }: server-side in-txn lint
 *       caught something the background lint missed (race on flight number,
 *       stale rows, etc.). Push the new report back into lintReport and
 *       reopen the dialog — `/commit` is the authoritative gate.
 *     → other error: surface to user, return to idle.
 *
 * `lintReport` is a store signal so the auto-lint effect can drive
 * RowLintIcon continuously; the dialog reads from it AND we write to it
 * after a /commit 422 response so live + dialog stay in sync.
 */

import { useState } from "preact/hooks";

import { ApiError, postCommit } from "../lib/api";
import { t } from "../lib/i18n";
import { buildLintPayload, regenerateRows, resetLifecycleState } from "../lib/lifecycle";
import { clearDraft, flushDraft } from "../state/persistence";
import { form, lintError, lintReport, rows } from "../state/store";
import type { CommitPayload, CommitResponse, LintPayload, LintReport } from "../state/types";
import { CommitSuccessRedirect } from "./CommitSuccessRedirect";
import { DirtyWarningDialog } from "./DirtyWarningDialog";
import { LintReportDialog } from "./LintReportDialog";
import { RowTable } from "./RowTable";

type CommitState =
  | { kind: "idle" }
  | { kind: "lint_review"; lintedPayload: LintPayload }
  | { kind: "committing"; lintedPayload: LintPayload }
  | { kind: "committed"; result: CommitResponse }
  | { kind: "error"; message: string };

export function PreviewPanel() {
  const [state, setState] = useState<CommitState>({ kind: "idle" });
  const [reviewDialogOpen, setReviewDialogOpen] = useState<boolean>(false);
  const list = rows.value;
  const report = lintReport.value;
  const lintErrMsg = lintError.value;
  const f = form.value;

  const rowCount = list.length;
  const errorCount = report?.errors.length ?? 0;
  const warningCount = report?.warnings.length ?? 0;

  function handleGenerate(): void {
    regenerateRows();
  }

  async function handleCreate(): Promise<void> {
    if (rowCount === 0 || f.airline_id === null) {
      return;
    }
    const payload = buildLintPayload();
    if (payload === null) {
      return;
    }
    flushDraft();
    // Reuse the background-lint report instead of a synchronous pre-commit
    // POST /lint. The auto-lint effect refreshes lintReport on a 400ms
    // debounce, so a user who just clicked Create is reading a recent
    // snapshot. /commit re-runs the full lint catalog inside its txn and
    // returns 422 with the fresh report if anything raced — that path is
    // the authoritative gate, so the pre-commit /lint round-trip adds
    // latency without adding safety.
    const currentReport = lintReport.value;
    if (
      currentReport !== null &&
      (currentReport.errors.length > 0 || currentReport.warnings.length > 0)
    ) {
      setState({ kind: "lint_review", lintedPayload: payload });
      return;
    }
    await doCommit(payload);
  }

  async function doCommit(lintedPayload: LintPayload): Promise<void> {
    // Commit the exact snapshot the user just reviewed/linted, not a fresh
    // rebuild from live store state. The store can drift between lint and
    // commit (e.g., the user types in a row); diverging here would commit
    // something the server didn't validate.
    const payload: CommitPayload = { ...lintedPayload, on_conflict: "abort" };
    setState({ kind: "committing", lintedPayload });
    try {
      const res = await postCommit(payload);
      // Clear UI state so a stray browser-back doesn't resurrect rows.
      clearDraft();
      resetLifecycleState();
      setState({ kind: "committed", result: res.data });
    } catch (err) {
      // Server-side lint caught something the client missed → re-open
      // the dialog with the fresh report so the user can see what.
      if (err instanceof ApiError && err.status === 422) {
        const body = err.body as { data?: LintReport } | null;
        if (body !== null && body.data !== undefined) {
          lintReport.value = body.data;
          setState({ kind: "lint_review", lintedPayload });
          return;
        }
      }
      setState({ kind: "error", message: describeError(err) });
    }
  }

  function handleLintCancel(): void {
    setState({ kind: "idle" });
  }

  // Committed branch takes over the whole pane — no table, no header.
  if (state.kind === "committed") {
    return (
      <div class="p-2">
        <CommitSuccessRedirect result={state.result} />
      </div>
    );
  }

  const lintDialogOpen = state.kind === "lint_review" || state.kind === "committing";
  const busy = state.kind === "committing";
  const canCreate = rowCount > 0 && f.airline_id !== null && errorCount === 0 && !busy;

  // Surface the first blocking reason next to the Generate button so the
  // user understands why it's greyed out instead of guessing.
  const generateBlockedReason = describeGenerateBlocker(f);
  const canGenerate = generateBlockedReason === null;

  return (
    <div class="flex flex-col gap-3">
      {/* Header */}
      <header class="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/40">
        <div class="text-sm text-gray-700 dark:text-gray-200">
          <span class="font-semibold">{rowCount}</span>{" "}
          {t(rowCount === 1 ? "preview.row_singular" : "preview.row_plural")}
          {report !== null && (
            <>
              {" · "}
              <LintCountButton
                tone="error"
                count={errorCount}
                disabled={errorCount === 0}
                onClick={() => setReviewDialogOpen(true)}
              />
              {" · "}
              <LintCountButton
                tone="warning"
                count={warningCount}
                disabled={warningCount === 0}
                onClick={() => setReviewDialogOpen(true)}
              />
            </>
          )}
        </div>
        <button
          type="button"
          class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-gray-300 dark:disabled:bg-gray-600"
          disabled={!canGenerate}
          title={generateBlockedReason ?? undefined}
          onClick={handleGenerate}
        >
          {t(rowCount === 0 ? "preview.generate" : "preview.regenerate")}
        </button>
      </header>

      {/* Generate-blocked hint */}
      {!canGenerate && (
        <div class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
          {generateBlockedReason}
        </div>
      )}

      {/* Background lint-check failure — non-blocking. Server commit is the
          authoritative gate, so Create stays clickable; this just tells the
          user RowLintIcon is unreliable until the next debounce lands. */}
      {lintErrMsg !== null && rowCount > 0 && (
        <div class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200">
          {lintErrMsg}
        </div>
      )}

      {/* Error banner */}
      {state.kind === "error" && (
        <div class="flex items-center justify-between rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-700 dark:bg-red-900/20 dark:text-red-200">
          <span>{state.message}</span>
          <button
            type="button"
            class="font-medium hover:underline"
            onClick={() => setState({ kind: "idle" })}
          >
            Dismiss
          </button>
        </div>
      )}

      {/* Table */}
      <RowTable />

      {/* Footer */}
      <footer class="flex flex-wrap items-center justify-between gap-3 rounded border border-gray-200 px-3 py-3 dark:border-gray-700">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
          <input
            type="checkbox"
            class="h-4 w-4"
            checked={f.bundle.enabled}
            onChange={(e) => {
              const enabled = (e.currentTarget as HTMLInputElement).checked;
              form.value = {
                ...f,
                bundle: { ...f.bundle, enabled, activate_on_save: enabled },
              };
            }}
          />
          {t("preview.activate")}
        </label>
        <button
          type="button"
          class="rounded bg-primary-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:bg-gray-300 dark:disabled:bg-gray-600"
          disabled={!canCreate}
          onClick={handleCreate}
        >
          {busy ? t("preview.working") : t("preview.create_button", { count: rowCount })}
        </button>
      </footer>

      {/* Dialogs */}
      <DirtyWarningDialog />
      <LintReportDialog
        open={lintDialogOpen}
        busy={state.kind === "committing"}
        onCancel={handleLintCancel}
        onProceed={() => {
          if (state.kind === "lint_review") {
            void doCommit(state.lintedPayload);
          }
        }}
      />
      <LintReportDialog
        open={reviewDialogOpen && !lintDialogOpen}
        busy={false}
        readOnly
        onCancel={() => setReviewDialogOpen(false)}
        onProceed={() => setReviewDialogOpen(false)}
      />

      {/* Block UI during commit if no dialog is shown */}
      {state.kind === "committing" && !lintDialogOpen && (
        <div class="fixed inset-0 z-40 bg-black/10" aria-hidden="true" />
      )}
    </div>
  );
}

// ─── Header lint-count button ─────────────────────────────────────────────

type LintCountButtonProps = {
  tone: "error" | "warning";
  count: number;
  disabled: boolean;
  onClick: () => void;
};

/**
 * Clickable error / warning counter in the PreviewPanel header. Opens
 * LintReportDialog in read-only mode so the user can inspect issues that
 * aren't attached to a row (L1 capacity, L3 empty subfleets, L7 no fares,
 * L9 batch-size warn, etc.) without going through the commit flow.
 * Disabled when count is zero so muted text stays inert.
 */
function LintCountButton({ tone, count, disabled, onClick }: LintCountButtonProps) {
  const noun = tone === "error" ? "error" : "warning";
  const activeColor =
    tone === "error"
      ? "text-red-600 hover:text-red-700 underline-offset-2 hover:underline dark:text-red-400"
      : "text-yellow-700 hover:text-yellow-800 underline-offset-2 hover:underline dark:text-yellow-300";
  const mutedColor = "text-gray-500 dark:text-gray-400";
  return (
    <button
      type="button"
      disabled={disabled}
      class={`bg-transparent p-0 ${disabled ? `cursor-default ${mutedColor}` : `cursor-pointer ${activeColor}`}`}
      onClick={onClick}
    >
      {count} {noun}
      {count === 1 ? "" : "s"}
    </button>
  );
}

// ─── Generate-button blocker hint ─────────────────────────────────────────

/**
 * Return the first reason the Generate button is disabled, or null when the
 * form has the minimum data needed to materialize rows. Order matches the
 * natural top-to-bottom form flow so the user is steered to the first thing
 * they need to fix.
 */
function describeGenerateBlocker(f: typeof form.value): string | null {
  if (f.airline_id === null) {
    return "Pick an airline first.";
  }
  if (f.origins.length === 0) {
    return "Add at least one origin airport.";
  }
  if (f.destinations.length === 0 && f.topology !== "tour") {
    return "Add at least one destination airport.";
  }
  if (f.topology === "tour" && f.origins.length < 2) {
    return "Tour mode needs at least 2 origins (A → B).";
  }
  return null;
}

function describeError(err: unknown): string {
  if (err instanceof ApiError) {
    return t("commit.error", { status: err.status });
  }
  if (err instanceof Error) {
    return err.message;
  }
  return "Unknown error.";
}
