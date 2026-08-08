/**
 * Inline saved-tick for autosaving form fields.
 *
 * AutosavesFields dispatches `autosaved` with the updated field's state
 * path after a successful save. Find that field's input wrapper and flash
 * a small check inside it; fields whose markup has no .fi-input-wrp
 * (file uploads, color pickers) get the tick pinned to the field wrapper
 * instead. Styling/animation live in theme.css (.autosave-tick).
 */
const TICK_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" ' +
  'stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">' +
  '<path d="M5 12l5 5l10 -10" /></svg>';

// wire:model attribute variants Filament emits for live()/live(onBlur) fields.
const MODEL_ATTRS = ["wire\\:model\\.live", "wire\\:model\\.blur", "wire\\:model"];

function fieldElement(statePath) {
  for (const attr of MODEL_ATTRS) {
    const el = document.querySelector(`[${attr}="${CSS.escape(statePath)}"]`);
    if (el) {
      return el;
    }
  }

  return null;
}

function showTick(statePath) {
  const el = fieldElement(statePath);
  if (!el) {
    return;
  }

  const host = el.closest(".fi-input-wrp") ?? el.closest(".fi-fo-field") ?? el.parentElement;
  if (!host || host.querySelector(":scope > .autosave-tick")) {
    return;
  }

  const tick = document.createElement("span");
  tick.className = "autosave-tick";
  tick.setAttribute("aria-hidden", "true");
  tick.innerHTML = TICK_SVG;
  tick.addEventListener("animationend", () => tick.remove(), { once: true });

  host.appendChild(tick);
}

document.addEventListener("livewire:init", () => {
  window.Livewire.on("autosaved", ({ statePath }) => {
    if (statePath) {
      showTick(statePath);
    }
  });
});
