/**
 * Generic help modal — shows a titled list of options with descriptions.
 *
 * Used by TopologyPicker and PresetPicker so the user can read every
 * option's effect side-by-side instead of cycling through them in the
 * select. Backdrop click or the Close button dismisses; the current
 * selection is visually highlighted so the user can anchor on it.
 *
 * Visuals + a11y mirror LintReportDialog so this stays consistent
 * without pulling in a real dialog library.
 */

import type { ComponentChildren } from "preact";

export type HelpModalItem = {
  /** Stable key used for highlighting the current selection + React keys. */
  key: string;
  /** Short heading shown in bold (e.g., "Hub → Spokes"). */
  label: string;
  /** Plain-text description; one sentence is best. */
  description: string;
};

export type HelpModalProps = {
  open: boolean;
  title: string;
  /** Optional subtitle/intro shown under the title. */
  subtitle?: string;
  items: HelpModalItem[];
  /** Item key matching the user's current selection (highlighted). */
  currentKey?: string;
  onClose: () => void;
};

export function HelpModal({ open, title, subtitle, items, currentKey, onClose }: HelpModalProps) {
  if (!open) {
    return null;
  }

  return (
    <ModalBackdrop onClick={onClose}>
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="rf-help-title"
        class="flex max-h-[80vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl dark:bg-gray-800"
        onClick={(e) => e.stopPropagation()}
      >
        <header class="border-b border-gray-200 px-5 py-3 dark:border-gray-700">
          <h2 id="rf-help-title" class="text-base font-semibold text-gray-900 dark:text-gray-100">
            {title}
          </h2>
          {subtitle !== undefined && subtitle !== "" && (
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>
          )}
        </header>
        <div class="flex-1 overflow-auto px-5 py-3">
          <ul class="space-y-2">
            {items.map((item) => {
              const isCurrent = item.key === currentKey;
              const ring = isCurrent
                ? "border-primary-400 bg-primary-50 dark:border-primary-500 dark:bg-primary-900/20"
                : "border-gray-200 dark:border-gray-700";
              return (
                <li key={item.key} class={`rounded border px-3 py-2 ${ring}`}>
                  <div class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {item.label}
                    {isCurrent && (
                      <span class="rounded bg-primary-600 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-white">
                        Selected
                      </span>
                    )}
                  </div>
                  <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{item.description}</p>
                </li>
              );
            })}
          </ul>
        </div>
        <footer class="flex justify-end gap-2 border-t border-gray-200 px-5 py-3 dark:border-gray-700">
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            onClick={onClose}
          >
            Close
          </button>
        </footer>
      </div>
    </ModalBackdrop>
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
