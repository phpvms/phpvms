/**
 * Theme picker — brand colour, appearance, density.
 *
 * Ported from mockups/admin-console-v2/theme-picker.js, trimmed to the three
 * sections the real admin panel keeps — see
 * resources/views/filament/plugins/theme-picker.blade.php for the markup.
 * Vanilla ESM, no Alpine dependency: the dropdown open/close itself is
 * Filament's own Alpine (x-filament::dropdown), left untouched here.
 *
 * State: only { brand, density } persist at STORE. Appearance (light/dark) is
 * NOT part of that state — it rides Filament's own mechanism instead
 * (vendor/filament/filament/resources/js/dark-mode.js): clicking a mode
 * button dispatches a `theme-changed` window CustomEvent whose `detail` is
 * 'light' | 'dark', which that script persists to localStorage 'theme' and
 * reflects as an Alpine.store('theme') effect that toggles <html class="dark">.
 */

const STORE = "phpvms-console-theme";

const DEFAULTS = {
  brand: "#067ec1",
  density: "compact",
};

// Shade name -> [percent of brand hex, mix colour]. 600 is the brand hex
// itself; lighter shades mix toward white, darker shades toward black.
// ponytail: a straight-line approximation of Filament's
// Color::generatePalette curve, not perceptually tuned per shade — good
// enough for a live client-side preview. Upgrade to a real palette generator
// if the approximation ever looks off against a saved server-side palette.
const SHADE_MIX = {
  50: [4, "white"],
  100: [8, "white"],
  200: [17, "white"],
  300: [30, "white"],
  400: [45, "white"],
  500: [70, "white"],
  600: [100, "white"],
  700: [85, "black"],
  800: [70, "black"],
  900: [55, "black"],
  950: [35, "black"],
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

function applyBrand(hex) {
  const root = document.documentElement.style;
  for (const [shade, [percent, mix]] of Object.entries(SHADE_MIX)) {
    root.setProperty(
      `--primary-${shade}`,
      percent >= 100 ? hex : `color-mix(in oklab, ${hex} ${percent}%, ${mix})`,
    );
  }
}

function apply(state) {
  applyBrand(state.brand);
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
  document.querySelectorAll(".fi-theme-picker [data-preset]").forEach((btn) => {
    btn.setAttribute(
      "aria-pressed",
      String(btn.dataset.preset.toLowerCase() === state.brand.toLowerCase()),
    );
  });

  const hexColor = document.querySelector(".fi-theme-picker [data-hex-color]");
  const hexText = document.querySelector(".fi-theme-picker [data-hex-text]");
  if (hexColor) {
    hexColor.value = state.brand;
  }
  if (hexText) {
    hexText.value = state.brand;
  }

  document.querySelectorAll(".fi-theme-picker [data-density]").forEach((btn) => {
    btn.setAttribute("aria-pressed", String(btn.dataset.density === state.density));
  });

  const mode = currentMode();
  document.querySelectorAll(".fi-theme-picker [data-mode]").forEach((btn) => {
    btn.setAttribute("aria-pressed", String(btn.dataset.mode === mode));
  });
}

let state = load();

// Applied before paint so brand/density don't flash the defaults first.
apply(state);

function update(patch) {
  Object.assign(state, patch);
  apply(state);
  save(state);
  syncControls(state);
}

document.addEventListener("click", (event) => {
  const preset = event.target.closest(".fi-theme-picker button[data-preset]");
  if (preset) {
    update({ brand: preset.dataset.preset });
    return;
  }

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

document.addEventListener("input", (event) => {
  if (event.target.matches(".fi-theme-picker [data-hex-color]")) {
    update({ brand: event.target.value });
  }
});

document.addEventListener("change", (event) => {
  if (!event.target.matches(".fi-theme-picker [data-hex-text]")) {
    return;
  }
  const value = event.target.value.trim();
  if (/^#[0-9a-f]{6}$/i.test(value)) {
    update({ brand: value });
  } else {
    event.target.value = state.brand;
  }
});

window.addEventListener("theme-changed", () => syncControls(state));

document.addEventListener("livewire:navigated", () => {
  apply(state);
  syncControls(state);
});

syncControls(state);
