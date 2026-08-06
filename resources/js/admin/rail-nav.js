/**
 * Rail hover flyouts for the collapsed desktop admin sidebar.
 *
 * Filament's `filamentDropdown` Alpine component (see
 * vendor/filament/support/resources/js/components/dropdown.js) already opens
 * a group's flyout on click via x-float. This module layers two things on
 * top, matching mockups/admin-console-v2/rail-menu.js:
 *
 *   1. Hover-to-open/hover-to-close, with short delays so passing over a
 *      group doesn't flash its flyout, and moving from the trigger into the
 *      flyout panel doesn't close it.
 *   2. Clicking the trigger button navigates straight to the group's first
 *      item instead of toggling the flyout (the flyout is still reachable
 *      by hovering).
 *
 * Everything is delegated on `document` rather than bound per-element, so it
 * keeps working after Livewire SPA navigation (`->spa()`) without needing to
 * listen for `livewire:navigated` and re-scan the DOM.
 *
 * Panels are not teleported (`x-filament::dropdown` is used without
 * `:teleport="true"` in vendor/filament/filament/resources/views/components
 * /sidebar/group.blade.php), so `.fi-dropdown-panel` stays a descendant of
 * its `li.fi-sidebar-group` — hovering the trigger and the panel both count
 * as "inside the group" for the purposes below.
 */

const OPEN_DELAY = 70;
const CLOSE_DELAY = 140;

let openTimer = null;
let closeTimer = null;
let openGroup = null; // the li.fi-sidebar-group currently flown out
let interceptedTrigger = null; // trigger whose mousedown we swallowed, awaiting the click

function railActive() {
  return (
    window.matchMedia("(hover: hover) and (pointer: fine)").matches &&
    window.innerWidth >= 1024 &&
    document.querySelector(".fi-sidebar") !== null &&
    document.querySelector(".fi-sidebar.fi-sidebar-open") === null
  );
}

function dropdownRoot(group) {
  return group.querySelector(":scope > .fi-dropdown");
}

function alpineDropdown(root) {
  return root && window.Alpine ? window.Alpine.$data(root) : null;
}

function firstItemUrl(group) {
  const item = group.querySelector(".fi-dropdown-panel a.fi-dropdown-list-item[href]");
  return item?.getAttribute("href") || null;
}

// Alpine's x-tooltip (Tippy) on the trigger button shows the same label as
// our flyout's header, so the two overlap. Tippy instances are created
// lazily on first hover and hang off the element as `el._tippy`, so this may
// be a no-op the very first time — the next hover (another "about to open"
// or click) calls it again once Tippy exists.
function suppressTooltip(trigger) {
  const btn = trigger.querySelector(".fi-sidebar-group-dropdown-trigger-btn");
  btn?._tippy?.hide();
  btn?._tippy?.disable();
}

function closeFlyout(group) {
  alpineDropdown(dropdownRoot(group))?.close();
  if (openGroup === group) {
    openGroup = null;
  }
}

function openFlyout(group) {
  if (group === openGroup) {
    return;
  }

  const root = dropdownRoot(group);
  const trigger = root?.querySelector(":scope > .fi-dropdown-trigger");
  const dropdown = alpineDropdown(root);
  if (!trigger || !dropdown) {
    return;
  }

  if (openGroup) {
    closeFlyout(openGroup);
  }

  suppressTooltip(trigger);

  // dropdown.js's open()/x-float's panel.open() both read `event.currentTarget`
  // as the anchor to position against — the same element Filament's own
  // `x-on:mousedown="toggle($event)"` would pass.
  dropdown.open({ currentTarget: trigger });
  openGroup = group;
}

function scheduleOpen(group) {
  window.clearTimeout(closeTimer);
  window.clearTimeout(openTimer);
  // Already showing one? Swap instantly, the intent is established.
  openTimer = window.setTimeout(() => openFlyout(group), openGroup ? 0 : OPEN_DELAY);
}

function scheduleClose() {
  window.clearTimeout(openTimer);
  window.clearTimeout(closeTimer);
  closeTimer = window.setTimeout(() => {
    if (openGroup) {
      closeFlyout(openGroup);
    }
  }, CLOSE_DELAY);
}

document.addEventListener("mouseover", (event) => {
  if (!railActive()) {
    return;
  }

  const group = event.target.closest("li.fi-sidebar-group");
  if (!group || !dropdownRoot(group) || group.contains(event.relatedTarget)) {
    return;
  }

  window.clearTimeout(closeTimer);
  scheduleOpen(group);
});

document.addEventListener("mouseout", (event) => {
  if (!railActive()) {
    return;
  }

  const group = event.target.closest("li.fi-sidebar-group");
  if (!group || !dropdownRoot(group) || group.contains(event.relatedTarget)) {
    return;
  }

  window.clearTimeout(openTimer);
  scheduleClose();
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape" || !openGroup) {
    return;
  }

  window.clearTimeout(openTimer);
  window.clearTimeout(closeTimer);
  closeFlyout(openGroup);
});

// Click-to-navigate. Filament's trigger wrapper opens the dropdown on
// MOUSEDOWN (`x-on:mousedown="if ($event.button === 0) toggle($event)"`), so
// we intercept mousedown in the capture phase — which always runs before a
// listener bound directly on the trigger — and stopImmediatePropagation to
// stop that toggle from firing. Navigation itself happens on the following
// click, once we know it's a plain left click. Middle-click is left alone:
// the trigger is a <button>, not a link, so it has no default action to
// preserve either way. Ctrl/Cmd+click opens the destination in a new tab.
document.addEventListener(
  "mousedown",
  (event) => {
    if (event.button !== 0 || !railActive()) {
      return;
    }

    const trigger = event.target.closest(".fi-dropdown-trigger");
    const group = trigger?.closest("li.fi-sidebar-group");
    if (!trigger || !group || !firstItemUrl(group)) {
      return; // no destination to jump to — leave the default toggle behaviour alone
    }

    event.stopImmediatePropagation();
    suppressTooltip(trigger);
    interceptedTrigger = trigger;
  },
  true,
);

document.addEventListener("click", (event) => {
  const trigger = interceptedTrigger;
  interceptedTrigger = null;

  if (!trigger || event.target.closest(".fi-dropdown-trigger") !== trigger) {
    return;
  }

  const group = trigger.closest("li.fi-sidebar-group");
  const url = group && firstItemUrl(group);
  if (!url) {
    return;
  }

  event.preventDefault();

  if (event.ctrlKey || event.metaKey) {
    window.open(url, "_blank");
  } else {
    window.location.assign(url);
  }
});
