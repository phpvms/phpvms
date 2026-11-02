/**
 * Sidebar shell behaviour for the admin console (mockups/admin-hybrid E).
 *
 *   1. Only the navigation group holding the active page starts open. Filament's
 *      own collapsible groups and its `filament:sidebar:collapsed_groups` store
 *      do the work; this just rewrites that list on load and on every
 *      `livewire:navigated`. Manual toggles still open any other group until
 *      the next navigation.
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

// Filament marks the active group (`fi-active`) server-side, so a plain rAF
// after Alpine settles is enough; `alpine:initialized` covers the first load.
document.addEventListener("alpine:initialized", collapseInactiveGroups);
document.addEventListener("livewire:navigated", () =>
  requestAnimationFrame(collapseInactiveGroups),
);
document.addEventListener("keydown", onKeydown);
