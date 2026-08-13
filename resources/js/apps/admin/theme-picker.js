/**
 * Theme picker — appearance and density. Brand colour moved to the Branding
 * admin page (site-wide, read from `App\Support\Branding` and applied via
 * Filament's own `->colors()` closure) — it is no longer a per-user
 * localStorage preference, so this file no longer touches it.
 *
 * Ported from mockups/admin-console-v2/theme-picker.js, trimmed to the two
 * sections the real admin panel keeps — see
 * resources/views/filament/plugins/theme-picker.blade.php for the markup.
 * Vanilla ESM, no Alpine dependency: the dropdown open/close itself is
 * Filament's own Alpine (x-filament::dropdown), left untouched here.
 *
 * State: only { density } persists at STORE. Appearance (light/dark) is NOT
 * part of that state — it rides Filament's own mechanism instead
 * (vendor/filament/filament/resources/js/dark-mode.js): clicking a mode
 * button dispatches a `theme-changed` window CustomEvent whose `detail` is
 * 'light' | 'dark', which that script persists to localStorage 'theme' and
 * reflects as an Alpine.store('theme') effect that toggles <html class="dark">.
 */

const STORE = "phpvms-console-theme";

const DEFAULTS = {
  density: "compact",
};

function load() {
  try {
    return { ...DEFAULTS, ...JSON.parse(localStorage.getItem(STORE) || "{}") };
  } catch {
    return { ...DEFAULTS };
  }
}

function save(state) {
  localStorage.setItem(STORE, JSON.stringify(state));
}

function apply(state) {
  document.documentElement.dataset.density = state.density;
}

// dark-mode.js writes localStorage 'theme' synchronously before touching the
// DOM, so reading it is more reliable than racing its Alpine.effect for the
// <html class="dark"> toggle. Falls back to the applied class for the
// 'system' case, where 'theme' doesn't say light or dark directly.
function currentMode() {
  const stored = localStorage.getItem("theme");
  return stored === "dark" || stored === "light"
    ? stored
    : document.documentElement.classList.contains("dark")
      ? "dark"
      : "light";
}

function syncControls(state) {
  document.querySelectorAll(".fi-theme-picker [data-density]").forEach((btn) => {
    btn.setAttribute("aria-pressed", String(btn.dataset.density === state.density));
  });

  const mode = currentMode();
  document.querySelectorAll(".fi-theme-picker [data-mode]").forEach((btn) => {
    btn.setAttribute("aria-pressed", String(btn.dataset.mode === mode));
  });
}

let state = load();

// Applied before paint so density doesn't flash the default first.
apply(state);

function update(patch) {
  Object.assign(state, patch);
  apply(state);
  save(state);
  syncControls(state);
}

document.addEventListener("click", (event) => {
  const mode = event.target.closest(".fi-theme-picker button[data-mode]");
  if (mode) {
    window.dispatchEvent(new CustomEvent("theme-changed", { detail: mode.dataset.mode }));
    syncControls(state);
    return;
  }

  const density = event.target.closest(".fi-theme-picker button[data-density]");
  if (density) {
    update({ density: density.dataset.density });
    return;
  }

  if (event.target.closest(".fi-theme-picker .fi-dropdown-trigger")) {
    syncControls(state);
  }
});

window.addEventListener("theme-changed", () => syncControls(state));

document.addEventListener("livewire:navigated", () => {
  apply(state);
  syncControls(state);
});

syncControls(state);
