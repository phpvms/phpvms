/**
 * Esc returns from a PIREP record page to the list, like the banner's
 * "Back to list" button (direction E).
 *
 * The button is the `[data-pirep-back]` link rendered in the record banner
 * (resources/views/filament/pireps/partials/record-banner.blade.php). Esc is
 * left alone while focus is in a field, or while any dialog is showing, so
 * closing a modal or clearing an input never navigates away.
 */

const EDITABLE = "input, textarea, select, [contenteditable=''], [contenteditable='true']";

/**
 * @param {Element | null} element
 * @returns {boolean}
 */
export function isEditing(element) {
  return element instanceof Element && element.closest(EDITABLE) !== null;
}

/**
 * @param {ParentNode} root
 * @returns {boolean}
 */
export function hasOpenDialog(root = document) {
  return [...root.querySelectorAll("[role='dialog'], dialog[open]")].some(
    (dialog) => dialog.getClientRects().length > 0,
  );
}

/**
 * @param {KeyboardEvent} event
 */
export function handleEscape(event) {
  if (event.key !== "Escape" || event.defaultPrevented) {
    return;
  }

  const back = document.querySelector("[data-pirep-back]");

  if (!back || isEditing(document.activeElement) || hasOpenDialog()) {
    return;
  }

  event.preventDefault();
  back.click();
}

document.addEventListener("keydown", handleEscape);
