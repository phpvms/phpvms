import type { StyleSpecification } from "maplibre-gl";
import type { MapLayerConfig } from "./types.ts";

/**
 * Combined style + overlay-layer attribution surface (design.md D9,
 * tasks.md 3.6). `createMap` does not render maplibre's own
 * `AttributionControl` — `../acars/.../MapLibreMap.svelte` dropped it because
 * it collided with their nav rail, and the credit moved into a menu footer
 * instead. The obligation is legal, not cosmetic, so this package owns an
 * attribution surface rather than dropping the control and the credit both.
 */

/** Every attribution string named by the resolved style's own sources. */
function styleAttributions(style: StyleSpecification): string[] {
  const sources = style.sources ?? {};
  const found: string[] = [];
  for (const source of Object.values(sources)) {
    const attribution = (source as { attribution?: string }).attribution;
    if (attribution) found.push(attribution);
  }
  return found;
}

/** Deduplicated, in order: style credits first, then each enabled overlay layer's own. */
export function collectAttributions(style: StyleSpecification, layers: MapLayerConfig[]): string[] {
  const layerAttributions = layers
    .map((l) => l.attribution)
    .filter((a): a is string => a !== null && a !== "");
  return Array.from(new Set([...styleAttributions(style), ...layerAttributions]));
}

const CONTAINER_CLASS = "phpvms-map-attribution";

/** Render the combined credit into `container` as a single line, replacing any previous content. */
export function renderAttribution(
  container: HTMLElement,
  style: StyleSpecification,
  layers: MapLayerConfig[],
): void {
  const attributions = collectAttributions(style, layers);
  container.replaceChildren();
  container.classList.add(CONTAINER_CLASS);
  if (attributions.length === 0) return;
  // Disabled until the attribution is rendered as text and anchor nodes instead
  // of innerHTML (CodeQL js/xss-through-dom): the style's source credits come
  // from a third-party style host. Follow-up on the OFP/Livewire rework branch.
  // container.innerHTML = attributions.join(" · ");
}
