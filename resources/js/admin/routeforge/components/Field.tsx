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
  /**
   * Renders a `?` help button after the label that calls this handler when
   * clicked. Use this when the help content is too long for a tooltip and
   * should open a modal instead. Takes precedence over `tooltip` when both
   * are set.
   */
  onHelpClick?: () => void;
  /** Accessible label for the help button (defaults to "Help"). */
  helpAriaLabel?: string;
};

/**
 * Tailwind utility string shared across <input>, <select>, <textarea>.
 * Centralised so a future theme audit (Section 7.2) updates one constant.
 *
 * These mirror Filament's `.fi-input-wrp` chrome (ring + shadow, not a border)
 * combined with `.fi-input`'s typography, so RouteForge controls match the rest
 * of the panel. Filament splits them across a wrapper div and the input; we
 * keep the single element and carry both, which means the values are copied
 * rather than inherited.
 *
 * ponytail: copied values drift if Filament restyles .fi-input-wrp. Swap to
 * real wrapper divs + fi-input-wrp/fi-input if that ever bites — the two can't
 * share one element because `input.fi-input` outranks `.fi-input-wrp` on
 * specificity and forces bg-transparent.
 *
 * Radius comes from --radius-lg, sizing from --text-sm, both set in
 * resources/css/filament/admin/theme.css.
 */
const INPUT_BASE =
  "block w-full appearance-none rounded-lg border-none bg-white px-3 py-1.5 text-sm leading-6 " +
  "text-gray-950 placeholder:text-gray-400 shadow-sm transition duration-75 " +
  "focus:outline-none disabled:cursor-not-allowed disabled:bg-gray-50 disabled:text-gray-500 " +
  "dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500 " +
  "dark:disabled:bg-transparent dark:disabled:text-gray-400";

export const INPUT_CLASS =
  `${INPUT_BASE} ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 ` +
  "dark:ring-white/20 dark:focus:ring-primary-500";

export const INPUT_CLASS_ERROR =
  `${INPUT_BASE} ring-1 ring-danger-600 focus:ring-2 focus:ring-danger-600 ` +
  "dark:ring-danger-500 dark:focus:ring-danger-500";

/**
 * Selects need Filament's `select.fi-select-input` on top, which supplies the
 * chevron background-image and the end padding that clears it. Without it
 * `appearance-none` strips the native arrow and leaves no dropdown affordance.
 *
 * Our utilities still win over that class's own background/padding: Filament
 * imports its CSS into layer(components), and Tailwind orders utilities after
 * components regardless of specificity.
 */
export const SELECT_CLASS = `${INPUT_CLASS} fi-select-input`;

export function Field({
  label,
  hint,
  htmlFor,
  children,
  error,
  required,
  tooltip,
  onHelpClick,
  helpAriaLabel,
}: FieldProps) {
  const helpIconClass =
    "inline-flex h-4 w-4 items-center justify-center rounded-full bg-gray-200 text-[10px] font-bold text-gray-600 hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600";

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
        {onHelpClick !== undefined ? (
          <button
            type="button"
            aria-label={helpAriaLabel ?? "Help"}
            onClick={onHelpClick}
            class={`${helpIconClass} cursor-pointer border-0 p-0`}
          >
            ?
          </button>
        ) : (
          tooltip !== undefined &&
          tooltip !== "" && (
            <Tooltip text={tooltip}>
              <span
                role="img"
                aria-label={tooltip}
                tabIndex={0}
                class={`${helpIconClass} cursor-help`}
              >
                ?
              </span>
            </Tooltip>
          )
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
