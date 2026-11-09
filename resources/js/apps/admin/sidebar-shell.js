/**
 * Sidebar shell behaviour for the admin console (mockups/admin-hybrid E).
 *
 *   1. On a first visit only the navigation group holding the active page
 *      starts open. Filament's collapsible groups persist every later toggle in
 *      `filament:sidebar:collapsed_groups`, so once that list exists it is left
 *      alone and the user's open/closed headings survive reloads.
 *   2. The `[` key toggles the icon rail through the same store the foot button
 *      uses, so Filament persists it as `filament:sidebar:desktop_open`.
 */

const TYPING_SELECTOR = "input, textarea, select, [contenteditable]:not([contenteditable='false'])";

export function isTypingContext(target) {
  return target instanceof Element && target.closest(TYPING_SELECTOR) !== null;
}

export function collapseInactiveGroups() {
  const store = window.Alpine?.store("sidebar");
  if (!store) {
    return;
  }

  const labels = Array.from(document.querySelectorAll(".fi-main-sidebar li.fi-sidebar-group"))
    .filter((group) => group.dataset.groupLabel && !group.classList.contains("fi-active"))
    .map((group) => group.dataset.groupLabel);

  store.collapsedGroups = labels;
}

/**
 * Filament's store starts `collapsedGroups` as null until something is saved,
 * so null is the only state that gets the default.
 */
export function seedCollapsedGroups() {
  if (window.Alpine?.store("sidebar")?.collapsedGroups !== null) {
    return;
  }

  collapseInactiveGroups();
}

export function toggleSidebar() {
  const store = window.Alpine?.store("sidebar");
  if (!store || store.isMobile) {
    return;
  }

  if (store.isOpen) {
    store.close();
  } else {
    store.open();
  }
}

function onKeydown(event) {
  if (
    event.key !== "[" ||
    event.ctrlKey ||
    event.metaKey ||
    event.altKey ||
    event.defaultPrevented
  ) {
    return;
  }

  if (isTypingContext(event.target)) {
    return;
  }

  event.preventDefault();
  toggleSidebar();
}

// Filament marks the active group (`fi-active`) server-side, so running once
// Alpine settles is enough.
document.addEventListener("alpine:initialized", seedCollapsedGroups);
document.addEventListener("keydown", onKeydown);
