/**
 * Shared form-field wrapper + canonical input class string.
 *
 * Used by every widget in components/ so labels, spacing, and the input
 * border/focus/dark-mode triad stay consistent without a dedicated stylesheet
 * (Section 7 directive: Tailwind utilities, no new CSS files).
 *
 * The `Field` wrapper uses a div + explicit <label htmlFor> rather than the
 * label-wraps-input pattern because some widgets (TimeStrategyControls, the
 * jitter row, AirportPicker's selected chips) render multiple inputs inside
 * one labeled group — wrapping <label> around multiple inputs is invalid HTML.
 */

import type { ComponentChildren } from "preact";

import { Tooltip } from "./Tooltip";

export type FieldProps = {
  label: string;
  hint?: string;
  htmlFor?: string;
  children: ComponentChildren;
  error?: string | null;
  /** Renders a red `*` after the label so the user knows the field must be filled before commit. */
  required?: boolean;
  /** Renders a `?` help icon after the label; hover shows the native browser tooltip with this text. */
  tooltip?: string;
};

/**
 * Tailwind utility string shared across <input>, <select>, <textarea>.
 * Centralised so a future theme audit (Section 7.2) updates one constant.
 */
export const INPUT_CLASS =
  "block w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm " +
  "text-gray-900 placeholder-gray-400 " +
  "focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 " +
  "disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500 " +
  "dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 " +
  "dark:disabled:bg-gray-900 dark:disabled:text-gray-600";

export const INPUT_CLASS_ERROR = INPUT_CLASS.replace("border-gray-300", "border-red-400").replace(
  "dark:border-gray-600",
  "dark:border-red-500",
);

export function Field({ label, hint, htmlFor, children, error, required, tooltip }: FieldProps) {
  return (
    <div class="mb-3">
      <label
        htmlFor={htmlFor}
        class="mb-1 flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-300"
      >
        <span>
          {label}
          {required === true && (
            <span class="ml-0.5 text-red-600 dark:text-red-400" aria-label="required">
              *
            </span>
          )}
        </span>
        {tooltip !== undefined && tooltip !== "" && (
          <Tooltip text={tooltip}>
            <span
              role="img"
              aria-label={tooltip}
              tabIndex={0}
              class="inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full bg-gray-200 text-[10px] font-bold text-gray-600 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
            >
              ?
            </span>
          </Tooltip>
        )}
      </label>
      {children}
      {hint !== undefined && (error === undefined || error === null || error === "") && (
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{hint}</p>
      )}
      {error !== undefined && error !== null && error !== "" && (
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>
      )}
    </div>
  );
}
