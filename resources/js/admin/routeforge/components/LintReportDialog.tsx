/**
 * Pre-commit lint summary modal.
 *
 * Opens from PreviewPanel's commit flow when the background lint report
 * (refreshed on a 400ms debounce by the auto-lint effect) carries any
 * errors or warnings. Two cases:
 *
 *   - **errors present** — header reads "Cannot commit". Proceed button
 *     disabled. User clicks Cancel + fixes the form / rows.
 *
 *   - **warnings only** — header reads "Lint warnings". Proceed button
 *     enabled, runs the commit POST.
 *
 * Per spec scenario "Server commit rejects payload with lint errors", the
 * server is the authoritative gate — this dialog mirrors what the background
 * lint already showed. If the user forces a commit anyway, /commit re-runs
 * the full lint catalog inside its txn and returns 422 + LintReport, at
 * which point PreviewPanel pushes the fresh report into `lintReport.value`
 * and re-opens this dialog.
 *
 * Issues group by severity for scannability. Rule code surfaces in mono
 * font so admin can cross-reference design.md's lint catalog.
 */

import type { ComponentChildren } from "preact";

import { t } from "../lib/i18n";
import { lintReport } from "../state/store";
import type { LintIssue, LintSeverity } from "../state/types";

export type LintReportDialogProps = {
  open: boolean;
  busy: boolean;
  onCancel: () => void;
  onProceed: () => void;
  /**
   * Read-only preview mode: hides the Proceed button and renames Cancel to
   * "Close". Used when the user clicks the header error/warning counts in
   * PreviewPanel to inspect lint output outside the commit flow.
   */
  readOnly?: boolean;
};

export function LintReportDialog({
  open,
  busy,
  onCancel,
  onProceed,
  readOnly,
}: LintReportDialogProps) {
  if (!open) {
    return null;
  }
  const report = lintReport.value;
  if (report === null) {
    return null;
  }

  const hasErrors = report.errors.length > 0;
  const title = t(hasErrors ? "lint_dialog.errors_title" : "lint_dialog.warnings_title");
  const subtitle = t(
    hasErrors ? "lint_dialog_extra.subtitle_errors" : "lint_dialog_extra.subtitle_warnings",
  );

  return (
    <ModalBackdrop onClick={onCancel}>
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="rf-lint-title"
        class="flex max-h-[80vh] w-full max-w-2xl flex-col rounded-lg bg-(--surface) shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <header class="border-b border-(--line) px-5 py-3">
          <h2 id="rf-lint-title" class="text-base font-semibold text-(--ink)">
            {title}
          </h2>
          <p class="mt-1 text-sm text-(--ink-3)">{subtitle}</p>
        </header>
        <div class="flex-1 overflow-auto px-5 py-3">
          <IssueSection
            title={t("lint_dialog_extra.section_errors")}
            severity="error"
            issues={report.errors}
          />
          <IssueSection
            title={t("lint_dialog_extra.section_warnings")}
            severity="warning"
            issues={report.warnings}
          />
          <IssueSection
            title={t("lint_dialog_extra.section_info")}
            severity="info"
            issues={report.info}
          />
          {report.errors.length === 0 &&
            report.warnings.length === 0 &&
            report.info.length === 0 && (
              <p class="text-sm text-(--ink-3)">{t("lint_dialog_extra.no_issues")}</p>
            )}
        </div>
        <footer class="flex justify-end gap-2 border-t border-(--line) px-5 py-3">
          <button type="button" class="fi-btn fi-color-gray" onClick={onCancel}>
            {readOnly === true ? t("lint_dialog_extra.close") : t("lint_dialog.cancel")}
          </button>
          {readOnly !== true && (
            <button
              type="button"
              class="fi-btn fi-color-primary"
              disabled={hasErrors || busy}
              onClick={onProceed}
            >
              {busy ? t("preview.working") : t("lint_dialog.proceed")}
            </button>
          )}
        </footer>
      </div>
    </ModalBackdrop>
  );
}

type IssueSectionProps = {
  title: string;
  severity: LintSeverity;
  issues: LintIssue[];
};

function IssueSection({ title, severity, issues }: IssueSectionProps) {
  if (issues.length === 0) {
    return null;
  }
  const tone =
    severity === "error"
      ? "border-(--bad-line) bg-(--bad-soft)"
      : severity === "warning"
        ? "border-(--warn-line) bg-(--warn-soft)"
        : "border-(--info-line) bg-(--info-soft)";
  const dot =
    severity === "error" ? "bg-(--bad)" : severity === "warning" ? "bg-(--warn)" : "bg-(--info)";

  return (
    <section class={`mb-3 rounded border ${tone}`}>
      <h3 class="border-b border-current/20 px-3 py-2 text-sm font-semibold text-(--ink-2)">
        {title} ({issues.length})
      </h3>
      <ul class="divide-y divide-current/10">
        {issues.map((issue, idx) => (
          <li key={`${issue.rule}-${idx}`} class="flex items-start gap-2 px-3 py-2 text-sm">
            <span class={`mt-1.5 h-2 w-2 flex-shrink-0 rounded-full ${dot}`} aria-hidden="true" />
            <div class="flex-1">
              <div class="flex items-center gap-2">
                <span class="font-mono text-xs font-bold text-(--ink-2)">{issue.rule}</span>
                {issue.row_index !== null && (
                  <span class="text-xs text-(--ink-3)">
                    {t("lint_dialog_extra.row")} {issue.row_index + 1}
                  </span>
                )}
              </div>
              <div class="text-(--ink)">{issue.message}</div>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}

type ModalBackdropProps = {
  children: ComponentChildren;
  onClick: () => void;
};

function ModalBackdrop({ children, onClick }: ModalBackdropProps) {
  return (
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      onClick={onClick}
    >
      {children}
    </div>
  );
}
