import type { ControlPosition, IControl, Map as MapLibreMapType } from "maplibre-gl";
import { layerId } from "./layers.ts";
import type { MapLayerConfig } from "./types.ts";

/**
 * A per-user overlay-layer picker (the operator's `map_layers` rows remain the
 * gate — this only lets a user hide layers the operator already enabled, never
 * reveal ones they disabled; `MapConfigService::resolve()` never ships a
 * disabled row to the browser in the first place).
 *
 * Preferences persist to `localStorage`, per browser, deliberately NOT to the
 * user record: this is a view preference, and a server round-trip per checkbox
 * would be the only thing on these pages that needs one.
 *
 * WHAT IS STORED IS THE HIDDEN SET, NOT THE VISIBLE ONE. With "hidden" as the
 * stored shape, absence means visible, so (a) a fresh browser shows exactly
 * what the operator configured, and (b) a layer the operator adds LATER is
 * visible immediately rather than silently off for every existing user until
 * they discover the menu. Storing the visible set would invert both.
 *
 * Styles are injected by this module rather than inherited from
 * `maplibre-gl.css`: only `fe-vue/src/app/app.css` imports that stylesheet, so
 * on the two admin surfaces (`apps/admin/phpvms-map.js`) maplibre's own
 * `.maplibregl-ctrl-*` rules are absent entirely and a control styled against
 * them would render as unstyled markup. Verified by grep before writing this,
 * not assumed.
 */

const STORAGE_KEY = "phpvms.map.hidden-layers";
const CONTROL_CLASS = "phpvms-map-layer-control";
const STYLE_ELEMENT_ID = "phpvms-map-layer-control-styles";

/**
 * `localStorage` is not always reachable — Safari's private mode historically
 * threw `QuotaExceededError` on write, and any browser with site data blocked
 * throws on mere ACCESS of `window.localStorage`. Every use goes through these
 * two helpers so a storage failure degrades to "no persistence" rather than
 * taking the map down with it.
 */
function safeStorage(): Storage | null {
  try {
    return globalThis.localStorage ?? null;
  } catch {
    return null;
  }
}

/** The set of layer ids the user has hidden. Unknown/corrupt storage reads as "nothing hidden". */
export function readHiddenLayerIds(storage: Storage | null = safeStorage()): Set<number> {
  if (!storage) return new Set();
  try {
    const raw = storage.getItem(STORAGE_KEY);
    if (!raw) return new Set();
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return new Set();
    return new Set(parsed.filter((id): id is number => typeof id === "number"));
  } catch {
    return new Set();
  }
}

export function writeHiddenLayerIds(
  hidden: Set<number>,
  storage: Storage | null = safeStorage(),
): void {
  if (!storage) return;
  try {
    storage.setItem(STORAGE_KEY, JSON.stringify([...hidden]));
  } catch {
    // full, or blocked — the toggle still applies to the live map, it just won't survive a reload
  }
}

/**
 * Push the hidden set onto the map. Guarded by `getLayer` because `applyLayers`
 * skips any layer whose source failed, so a configured layer is not guaranteed
 * to exist on the style — `setLayoutProperty` on a missing id throws.
 */
export function applyLayerVisibility(
  map: MapLibreMapType,
  layers: MapLayerConfig[],
  hidden: Set<number>,
): void {
  for (const layer of layers) {
    const id = layerId(layer);
    if (!map.getLayer(id)) continue;
    map.setLayoutProperty(id, "visibility", hidden.has(layer.id) ? "none" : "visible");
  }
}

/**
 * Inject the control's stylesheet once per document.
 *
 * `pointer-events: auto` on the container is REQUIRED, not cosmetic.
 * maplibre-gl.css sets `.maplibregl-ctrl-top-right { pointer-events: none }`
 * on the corner container `addControl` drops this into, and restores it only
 * on its own `.maplibregl-ctrl` class. Without it the toggle is invisible to
 * mouse hit-testing and a click falls through to the canvas — rotating the
 * globe instead of opening the panel — while STILL working by keyboard, since
 * pointer-events affects neither focus nor key activation. That split is what
 * made it easy to miss.
 *
 * Only fe-vue loads maplibre-gl.css (`src/app/app.css`), so this reproduced on
 * skylight and not on the admin maps. Fixed here rather than by adding
 * maplibre's own `.maplibregl-ctrl` class to the container: this package
 * styles itself so both surfaces behave identically, and depending on a
 * stylesheet only one of them loads is exactly what produced the bug. The
 * margin is self-hosted for the same reason — maplibre gives its own controls
 * one, and without it this sits jammed in the corner on admin.
 */
function injectStyles(doc: Document): void {
  if (doc.getElementById(STYLE_ELEMENT_ID)) return;
  const style = doc.createElement("style");
  style.id = STYLE_ELEMENT_ID;
  style.textContent = `
.${CONTROL_CLASS} {
  position: relative;
  /* pointer-events is REQUIRED here — see injectStyles' doc comment. */
  pointer-events: auto;
  margin: 10px 10px 0 0;
}
.${CONTROL_CLASS}__toggle {
  display: flex; align-items: center; justify-content: center;
  width: 29px; height: 29px; padding: 0;
  border: 0; border-radius: 4px; cursor: pointer;
  background: var(--phpvms-map-control-bg, #fff);
  color: var(--phpvms-map-control-fg, #333);
  box-shadow: 0 0 0 2px rgb(0 0 0 / 10%);
}
.${CONTROL_CLASS}__toggle:hover { background: var(--phpvms-map-control-bg-hover, #f2f2f2); }
.${CONTROL_CLASS}__toggle:focus-visible { outline: 2px solid var(--phpvms-map-control-accent, #0284c7); outline-offset: 2px; }
.${CONTROL_CLASS}__panel {
  position: absolute; top: 0; right: 33px; z-index: 1;
  min-width: 11rem; padding: 8px;
  border-radius: 4px;
  background: var(--phpvms-map-control-bg, #fff);
  color: var(--phpvms-map-control-fg, #333);
  box-shadow: 0 0 0 2px rgb(0 0 0 / 10%);
  font: 12px/1.4 system-ui, sans-serif;
}
.${CONTROL_CLASS}__panel[hidden] { display: none; }
.${CONTROL_CLASS}__title { margin: 0 0 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.7; }
.${CONTROL_CLASS}__item { display: flex; align-items: center; gap: 6px; padding: 3px 0; cursor: pointer; }
.${CONTROL_CLASS}__empty { margin: 0; opacity: 0.7; }
`.trim();
  doc.head.append(style);
}

/** maplibre's own icon set isn't available without its stylesheet, so the glyph is inline SVG. */
function layersIcon(doc: Document): SVGSVGElement {
  const svg = doc.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("width", "16");
  svg.setAttribute("height", "16");
  svg.setAttribute("fill", "none");
  svg.setAttribute("stroke", "currentColor");
  svg.setAttribute("stroke-width", "2");
  svg.setAttribute("stroke-linecap", "round");
  svg.setAttribute("stroke-linejoin", "round");
  svg.setAttribute("aria-hidden", "true");
  for (const d of ["m12 2 9 5-9 5-9-5 9-5Z", "m3 12 9 5 9-5", "m3 17 9 5 9-5"]) {
    const path = doc.createElementNS("http://www.w3.org/2000/svg", "path");
    path.setAttribute("d", d);
    svg.append(path);
  }
  return svg;
}

export type LayerControlOptions = {
  layers: MapLayerConfig[];
  /** Overridable so tests can drive persistence without a real `localStorage`. */
  storage?: Storage | null;
  label?: string;
};

export class LayerControl implements IControl {
  private container?: HTMLElement;
  private panel?: HTMLElement;
  private map?: MapLibreMapType;
  private readonly hidden: Set<number>;

  constructor(private readonly options: LayerControlOptions) {
    this.hidden = readHiddenLayerIds(options.storage ?? safeStorage());
  }

  getDefaultPosition(): ControlPosition {
    return "top-right";
  }

  onAdd(map: MapLibreMapType): HTMLElement {
    this.map = map;
    const doc = globalThis.document;
    injectStyles(doc);

    const container = doc.createElement("div");
    container.className = CONTROL_CLASS;

    const toggle = doc.createElement("button");
    toggle.type = "button";
    toggle.className = `${CONTROL_CLASS}__toggle`;
    toggle.setAttribute("aria-expanded", "false");
    toggle.title = this.options.label ?? "Map layers";
    toggle.setAttribute("aria-label", toggle.title);
    toggle.append(layersIcon(doc));

    const panel = doc.createElement("div");
    panel.className = `${CONTROL_CLASS}__panel`;
    panel.hidden = true;

    const title = doc.createElement("p");
    title.className = `${CONTROL_CLASS}__title`;
    title.textContent = this.options.label ?? "Map layers";
    panel.append(title);

    if (this.options.layers.length === 0) {
      const empty = doc.createElement("p");
      empty.className = `${CONTROL_CLASS}__empty`;
      empty.textContent = "No overlay layers configured.";
      panel.append(empty);
    }

    for (const layer of this.options.layers) {
      const label = doc.createElement("label");
      label.className = `${CONTROL_CLASS}__item`;
      const checkbox = doc.createElement("input");
      checkbox.type = "checkbox";
      checkbox.checked = !this.hidden.has(layer.id);
      checkbox.addEventListener("change", () => {
        if (checkbox.checked) {
          this.hidden.delete(layer.id);
        } else {
          this.hidden.add(layer.id);
        }
        writeHiddenLayerIds(this.hidden, this.options.storage ?? safeStorage());
        if (this.map) applyLayerVisibility(this.map, this.options.layers, this.hidden);
      });
      label.append(checkbox, doc.createTextNode(layer.name));
      panel.append(label);
    }

    toggle.addEventListener("click", () => {
      panel.hidden = !panel.hidden;
      toggle.setAttribute("aria-expanded", String(!panel.hidden));
    });

    container.append(toggle, panel);
    this.container = container;
    this.panel = panel;

    // The stored set has to reach the map on load too, not only on a click.
    applyLayerVisibility(map, this.options.layers, this.hidden);
    return container;
  }

  onRemove(): void {
    this.container?.remove();
    this.container = undefined;
    this.panel = undefined;
    this.map = undefined;
  }
}
