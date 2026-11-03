/**
 * UTC clock in resources/views/filament/shared/rail-clock.blade.php
 * (PanelsRenderHook::SIDEBAR_START), matching
 * mockups/admin-console-v2/utc-clock.js.
 *
 * Dispatch runs on UTC, so the console shows it without being asked. Ticks
 * are aligned to the second boundary rather than a flat 1000ms interval, so
 * they don't drift against the wall clock.
 *
 * Clicking the zone label toggles between UTC and local time. The choice
 * lives in a module variable (not storage), so it survives SPA navigations
 * but resets on a full page load.
 *
 * `->spa()` swaps the DOM on navigation without a full page load, so the
 * `<time>` element is re-found on `livewire:navigated` rather than cached —
 * the one self-scheduling timer just starts rendering into whichever element
 * is live.
 */

let el = null;
let zoneEl = null;
let useLocal = false;

function render() {
  if (!el) {
    return;
  }

  const now = new Date();
  const p = (n) => String(n).padStart(2, "0");
  const hh = p(useLocal ? now.getHours() : now.getUTCHours());
  const mm = p(useLocal ? now.getMinutes() : now.getUTCMinutes());
  const ss = p(useLocal ? now.getSeconds() : now.getUTCSeconds());

  el.dateTime = now.toISOString();
  if (zoneEl) {
    zoneEl.textContent = useLocal ? "Local" : "UTC";
  }
  el.innerHTML = `${hh}:${mm}<span class="fi-rail-clock-sec">:${ss}</span>`;
}

function schedule() {
  setTimeout(
    () => {
      render();
      schedule();
    },
    1000 - (Date.now() % 1000),
  );
}

function findElement() {
  el = document.querySelector(".fi-rail-clock time");
  zoneEl = document.querySelector(".fi-rail-clock-zone");
  render();
}

document.addEventListener("click", (event) => {
  if (!event.target.closest(".fi-rail-clock-zone")) {
    return;
  }

  useLocal = !useLocal;
  render();
});

document.addEventListener("DOMContentLoaded", findElement);
document.addEventListener("livewire:navigated", findElement);
schedule();
