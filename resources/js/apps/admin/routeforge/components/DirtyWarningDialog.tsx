/**
 * Dirty warning modal.
 *
 * Auto-mounts when `dirtyDialogOpen.value === true`. The lifecycle's dirty
 * tracker flips the signal when a generator-affecting form change happens
 * AND at least one row carries `edited: true`.
 *
 * Spec scenario (route-forge-tool):
 *   - Form change after generation surfaces the dirty-warning modal
 *   - Confirming regeneration wipes row edits
 *
 * Two actions:
 *   - **Confirm regenerate** — calls `regenerateRows()`. New rows replace
 *     old (edited rows lost). Dialog closes.
 *   - **Cancel** — just closes the dialog. The form stays changed, the
 *     rows stay edited, and PreviewPanel's "form changed since generate"
 *     banner remains so the user knows the rows are stale.
 */

import type { ComponentChildren } from "preact";

import { t } from "../lib/i18n";
import { dirtyDialogOpen, regenerateRows } from "../lib/lifecycle";
import { rows } from "../state/store";

export function DirtyWarningDialog() {
  if (!dirtyDialogOpen.value) {
    return null;
  }

  const editedCount = rows.value.filter((r) => r.edited).length;

  function handleConfirm(): void {
    regenerateRows();
  }

  function handleCancel(): void {
    dirtyDialogOpen.value = false;
  }

  return (
    <ModalBackdrop onClick={handleCancel}>
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="rf-dirty-title"
        class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl dark:bg-gray-800"
        onClick={(e) => e.stopPropagation()}
      >
        <h2
          id="rf-dirty-title"
          class="mb-2 text-base font-semibold text-gray-900 dark:text-gray-100"
        >
          {t("dirty.title")}
        </h2>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
          {t("dirty.body", { count: editedCount })}
        </p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            onClick={handleCancel}
          >
            {t("dirty.cancel")}
          </button>
          <button
            type="button"
            class="rounded bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700"
            onClick={handleConfirm}
          >
            {t("dirty.confirm")}
          </button>
        </div>
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
