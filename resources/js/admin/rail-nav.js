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
 *      by hovering). That jump goes through Livewire.navigate when the item
 *      itself carries wire:navigate, so the shortcut behaves like clicking the
 *      item rather than forcing a document load past the panel's ->spa().
 *
 * Everything is delegated on `document` rather than bound per-element, so the
 * listeners keep working after Livewire SPA navigation (`->spa()`) without
 * needing to re-scan the DOM. The module state below does NOT come along for
 * free, though — see resetFlyoutState().
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

// `Alpine.$data()` is `mergeProxies(closestDataStack(el))`, and that Proxy is
// TRUTHY even when the stack is empty — every property just reads as
// `undefined`. So a `?.` on the result never short-circuits and the caller ends
// up calling `undefined()`. An empty stack is precisely what a torn-down
// component looks like (Alpine's `x-data` cleanup runs
// `_x_undoAddScopeToNode()`, which filters the scope back out of
// `_x_dataStack` rather than restoring anything), and also what one Alpine has
// not reached yet looks like. Probe for the methods we actually call so every
// caller's null check is real.
function alpineDropdown(root) {
  if (!root || !window.Alpine) {
    return null;
  }

  const data = window.Alpine.$data(root);

  return typeof data?.open === "function" && typeof data?.close === "function" ? data : null;
}

function firstItem(group) {
  return group.querySelector(".fi-dropdown-panel a.fi-dropdown-list-item[href]");
}

function firstItemUrl(group) {
  return firstItem(group)?.getAttribute("href") || null;
}

// Whether Filament rendered this item for SPA navigation. `->spa()` puts
// `wire:navigate` on the anchor -- `wire:navigate.hover` when prefetching is
// enabled (see BasePanelProvider: `->spa(hasPrefetching: ...)`) -- and omits it
// entirely when the panel is not an SPA. Reading it off the element keeps this
// shortcut on whatever the panel is configured for instead of hardcoding one.
function navigatesInPlace(item) {
  return Array.from(item.attributes).some((attr) => attr.name.startsWith("wire:navigate"));
}

// Alpine's x-tooltip (Tippy) on the trigger button shows the same label as our
// flyout's header, so the two overlap.
//
// This has to run on the *mouseover*, not when the flyout actually opens
// OPEN_DELAY later: Filament registers the tooltip plugin without calling
// `defaultProps`, so Tippy keeps its stock `delay: 0` and paints the label the
// moment the pointer lands. Suppressing it only at open time left the label
// visible for the whole delay — the flicker where Filament's tooltip appears
// just before the flyout. Ordering works out because the spec dispatches
// `mouseover` (which we take on `document`) before `mouseenter` (which Tippy
// binds on the button), so a hover finds the instance already disabled.
//
// Re-disabling on every hover also covers the plugin re-enabling itself: its
// directive effect calls `enable()` whenever the bound expression re-evaluates
// to an object, and the blade builds a fresh object literal each time its
// `x-effect` reruns (on `$store.sidebar.isOpen` or `$store.theme`).
function suppressTooltip(group) {
  const btn = group.querySelector(".fi-sidebar-group-dropdown-trigger-btn");
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

  suppressTooltip(group);

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

  // Before Tippy's own `mouseenter` gets a turn — see suppressTooltip().
  suppressTooltip(group);

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

// Drop every reference to a flyout that is about to disappear.
//
// Deliberately does NOT close it. Filament's dropdown component closes itself
// on this same event, and a second close in the same tick is not a harmless
// no-op: Alpine's `_x_toggleAndCascadeWithTransitions` stores the pending
// `_x_hidePromise` on the element and only attaches the `isFromCancelledTransition`
// catch on the following animation frame, reading whatever `_x_hidePromise`
// holds *by then*. A second close overwrites that property and cancels the
// in-flight transition, so the first promise rejects with nobody listening —
// the "Uncaught (in promise) {isFromCancelledTransition: true}" console noise.
// Closing is Filament's job here; ours is only to forget the node.
function resetFlyoutState() {
  window.clearTimeout(openTimer);
  window.clearTimeout(closeTimer);
  openGroup = null;
  interceptedTrigger = null;
}

// Livewire's SPA navigation swaps the whole <body>, and Filament's sidebar is a
// plain @livewire() component with no @persist, so every `li.fi-sidebar-group`
// is destroyed and rebuilt. The delegated listeners in this file survive that;
// the module state above does not get the same treatment for free — `openGroup`
// would keep pointing at a node Livewire had already thrown away, and the next
// hover would talk to it.
//
// `livewire:navigate` fires synchronously, before the swap, on every navigation
// path: a wire:navigate link press, a programmatic `Livewire.navigate()`, and
// back/forward for both cached and uncached pages. Because it lands while the
// current flyout is still live, it closes cleanly here rather than leaving a
// detached subtree behind. It is also the hook Filament's own dropdown uses to
// close itself (vendor/filament/support/resources/js/components/dropdown.js).
document.addEventListener("livewire:navigate", resetFlyoutState);

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
    suppressTooltip(group);
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
  const item = group && firstItem(group);
  const url = item?.getAttribute("href");
  if (!url) {
    return;
  }

  event.preventDefault();

  if (event.ctrlKey || event.metaKey) {
    window.open(url, "_blank");

    return;
  }

  if (!navigatesInPlace(item) || !window.Livewire?.navigate) {
    window.location.assign(url);

    return;
  }

  // Livewire.navigate is the programmatic form of clicking a wire:navigate
  // link, so the shortcut lands on the same page the flyout item would have.
  // It is a getter for Alpine.navigate, which fires `livewire:navigate`
  // synchronously before it does anything else -- so resetFlyoutState() above
  // has already run by the time this returns and there is nothing to clean up
  // here.
  window.Livewire.navigate(url);
});
