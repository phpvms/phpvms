/**
 * PIREP slide-over panel (PeekAction): keyboard shortcuts while it is open,
 * plus ctrl/cmd/middle-click on a list row opening the full page in a new tab.
 *
 * Filament renders a record action as a button and drops the row URL, so the
 * row's own ident link (still an <a>) is the source of the full-page URL.
 */

const KEY_TARGETS = {
  j: '[data-pirep-nav="next"]',
  k: '[data-pirep-nav="previous"]',
  o: "[data-pirep-open]",
  a: '[data-pirep-decide="accept"]',
  r: '[data-pirep-decide="reject"]',
};

/** True when the event target is somewhere the user is typing. */
export function isTypingTarget(target) {
  if (!target || typeof target.closest !== "function") {
    return false;
  }

  return Boolean(
    target.closest("input, textarea, select, [contenteditable]:not([contenteditable='false'])"),
  );
}

/**
 * Click the control a key maps to. Returns true when a control was clicked.
 * Disabled and absent controls (no neighbour, not pending, not authorised)
 * are skipped, so the shortcuts follow the buttons' own availability.
 */
export function handlePanelKey(event, root = document) {
  if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey) {
    return false;
  }

  const selector = KEY_TARGETS[event.key?.toLowerCase()];

  if (!selector || isTypingTarget(event.target)) {
    return false;
  }

  const strip = root.querySelector("[data-pirep-strip]");
  const control = strip?.querySelector(selector);

  if (!control || control.disabled || control.getAttribute("aria-disabled") === "true") {
    return false;
  }

  event.preventDefault();
  control.click();

  return true;
}

/** Full-page URL for a clicked list row, or null when the click is not a new-tab one. */
export function newTabUrl(event) {
  const isMiddle = event.type === "auxclick" && event.button === 1;
  const isModified = event.type === "click" && (event.ctrlKey || event.metaKey);

  if (!isMiddle && !isModified) {
    return null;
  }

  const target = event.target;

  if (!target?.closest || target.closest("a[href]")) {
    return null;
  }

  const row = target.closest("[data-pirep-table] .fi-ta-row, [data-pirep-table] .fi-ta-record");

  return row?.querySelector("a[href]")?.href ?? null;
}

function openInNewTab(event) {
  const url = newTabUrl(event);

  if (url === null) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
  window.open(url, "_blank", "noopener");
}

if (typeof document !== "undefined") {
  document.addEventListener("keydown", (event) => handlePanelKey(event));
  document.addEventListener("click", openInNewTab, true);
  document.addEventListener("auxclick", openInNewTab, true);
}
