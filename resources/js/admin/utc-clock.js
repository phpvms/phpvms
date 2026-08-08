/**
 * UTC clock in resources/views/filament/shared/rail-clock.blade.php
 * (PanelsRenderHook::SIDEBAR_START), matching
 * mockups/admin-console-v2/utc-clock.js.
 *
 * Dispatch runs on UTC, so the console shows it without being asked. Ticks
 * are aligned to the second boundary rather than a flat 1000ms interval, so
 * they don't drift against the wall clock.
 *
 * `->spa()` swaps the DOM on navigation without a full page load, so the
 * `<time>` element is re-found on `livewire:navigated` rather than cached —
 * the one self-scheduling timer just starts rendering into whichever element
 * is live.
 */

let el = null;

function render() {
  if (!el) {
    return;
  }

  const now = new Date();
  const p = (n) => String(n).padStart(2, "0");
  const hh = p(now.getUTCHours());
  const mm = p(now.getUTCMinutes());
  const ss = p(now.getUTCSeconds());

  el.dateTime = now.toISOString();
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
  render();
}

document.addEventListener("DOMContentLoaded", findElement);
document.addEventListener("livewire:navigated", findElement);
schedule();
