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
        class="w-full max-w-md rounded-lg bg-(--surface) p-5 shadow-xl"
        onClick={(e) => e.stopPropagation()}
      >
        <h2 id="rf-dirty-title" class="mb-2 text-base font-semibold text-(--ink)">
          {t("dirty.title")}
        </h2>
        <p class="mb-4 text-sm text-(--ink-2)">{t("dirty.body", { count: editedCount })}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="fi-btn fi-color-gray" onClick={handleCancel}>
            {t("dirty.cancel")}
          </button>
          <button type="button" class="fi-btn fi-color-primary" onClick={handleConfirm}>
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
