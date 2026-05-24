/**
 * Per-row lint indicator.
 *
 * Filters the active `lintReport` for issues attached to this row's index
 * and renders a single icon — red for any error, yellow for warnings only,
 * nothing when clean or when no report exists yet. Hover/focus reveals a
 * popover listing every issue's rule code + message.
 *
 * Accessibility v1: hover-revealed popover via Tailwind `group-hover` /
 * `group-focus-within` so keyboard users can tab to the icon and the
 * tooltip appears. Not a full ARIA listbox — the lint dialog (6.3.16) is
 * the accessible canonical view; this is a glanceable row hint.
 */

import { lintReport } from "../state/store";
import type { LintIssue } from "../state/types";

export type RowLintIconProps = {
  rowIndex: number;
};

export function RowLintIcon({ rowIndex }: RowLintIconProps) {
  const report = lintReport.value;
  if (report === null) {
    return null;
  }

  const issues: LintIssue[] = [...report.errors, ...report.warnings, ...report.info].filter(
    (i) => i.row_index === rowIndex,
  );

  if (issues.length === 0) {
    return null;
  }

  const hasError = issues.some((i) => i.severity === "error");
  const colourClass = hasError
    ? "text-red-600 dark:text-red-400"
    : "text-yellow-600 dark:text-yellow-400";
  const glyph = hasError ? "⚠" : "⚡";
  const summary = hasError
    ? `${issues.filter((i) => i.severity === "error").length} error(s)`
    : `${issues.length} warning(s)`;

  return (
    <span class={`group relative inline-block ${colourClass}`} tabIndex={0} aria-label={summary}>
      <span class="cursor-help" aria-hidden="true">
        {glyph}
      </span>
      <span
        role="tooltip"
        class="invisible absolute right-0 top-full z-20 mt-1 w-64 rounded bg-gray-900 p-2 text-xs text-white shadow-lg group-hover:visible group-focus-within:visible dark:bg-gray-100 dark:text-gray-900"
      >
        {issues.map((i, idx) => (
          <div key={`${i.rule}-${idx}`} class="mb-1 last:mb-0">
            <span class="font-mono font-bold">{i.rule}</span>{" "}
            <span
              class={
                i.severity === "error"
                  ? "text-red-300 dark:text-red-700"
                  : "text-yellow-300 dark:text-yellow-700"
              }
            >
              ({i.severity})
            </span>
            <div>{i.message}</div>
          </div>
        ))}
      </span>
    </span>
  );
}
