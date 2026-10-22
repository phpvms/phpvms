import { onUnmounted, watch, type Ref } from "vue";
// v6 is ESM-only and drops the default export — named import (v5-to-v6 migration guide).
import { Marker } from "maplibre-gl";
import type { Map as MapLibreMapType } from "maplibre-gl";
import { bearing, type LngLat } from "@/shared/lib/geo";

/**
 * Origin/destination ring markers + a bearing-rotated plane glyph at the
 * origin — the DOM-marker half of the retired `useGlobe.ts` (maplibre-map-
 * platform design.md D12), factored out so both `NavDisplay.vue` and
 * `RouteWidget.vue` draw the identical markers over their own `@phpvms/map`
 * Tier 0 route line instead of duplicating this construction twice.
 *
 * Plain maplibre `Marker` DOM elements, not the package's `markers.ts`
 * (troika text) — that module needs a caller-supplied `LabelFont.url`
 * (design.md open question 4, unresolved), and these are simple ICAO text
 * labels that don't need a 3D/camera-facing label anyway.
 *
 * Styled with INLINE styles (reading the same `--pv-*` custom properties the
 * old `useGlobe.ts` styled `.mk-ring`/`.mk-apt`/`.mk-plane` with via a global,
 * unscoped `<style>` block) rather than a shared CSS class. A global block
 * only ships in a page's bundle when the SFC that declares it is actually
 * imported there — `NavDisplay.vue` previously owned those classes, but
 * `RouteWidget.vue` (the one consumer that IS wired to a page, the dashboard)
 * never imports `NavDisplay.vue`, so relying on that block would leave
 * RouteWidget's markers unstyled on the one page that renders them. Inline
 * styles need no shared stylesheet, so both consumers render correctly on
 * their own.
 *
 * `route` may be `null` (no origin known yet, e.g. RouteWidget with no home
 * airport) — markers are cleared and nothing is drawn until it resolves.
 */
export type RouteMarkersInput = {
  from: LngLat;
  to?: LngLat | null;
  fromLabel?: string;
  toLabel?: string;
};

/** A ring with a filled centre dot — `--pv-accent`, sized like the retired `.mk-ring`/`.mk-ring::after`. */
function ringMarkerEl(): HTMLDivElement {
  const el = document.createElement("div");
  Object.assign(el.style, {
    width: "12px",
    height: "12px",
    border: "1.6px solid var(--pv-accent)",
    borderRadius: "50%",
    position: "relative",
  });
  const dot = document.createElement("div");
  Object.assign(dot.style, {
    position: "absolute",
    inset: "3px",
    background: "var(--pv-accent)",
    borderRadius: "50%",
  });
  el.appendChild(dot);
  return el;
}

/** An ICAO text label, sized like the retired `.mk-apt`. */
function labelMarkerEl(text: string): HTMLDivElement {
  const el = document.createElement("div");
  el.textContent = text;
  Object.assign(el.style, {
    fontFamily: "var(--pv-font-mono)",
    fontSize: "calc(11px * var(--pv-type-scale))",
    color: "var(--pv-accent)",
    whiteSpace: "nowrap",
    transform: "translateY(-14px)",
    pointerEvents: "none",
  });
  return el;
}

/** A plane glyph rotated to the initial great-circle bearing, sized like the retired `.mk-plane`. */
function planeMarkerEl(rotationDeg: number): HTMLDivElement {
  const el = document.createElement("div");
  el.style.color = "var(--pv-accent)";
  el.style.transform = `rotate(${rotationDeg}deg)`;
  el.innerHTML =
    '<svg width="18" height="18" viewBox="-9 -9 18 18"><path d="M0 -8 L2.2 2.6 L8 5.4 L2.2 5.4 L0 10.5 L-2.2 5.4 L-8 5.4 L-2.2 2.6 Z" fill="currentColor"/></svg>';
  return el;
}

export function useRouteMarkers(
  map: Ref<MapLibreMapType | undefined>,
  route: Ref<RouteMarkersInput | null>,
): void {
  const markers: Marker[] = [];

  function clear() {
    for (const marker of markers.splice(0)) marker.remove();
  }

  watch(
    [map, route],
    ([mapInstance, input]) => {
      clear();
      if (!mapInstance || !input) return;

      markers.push(
        new Marker({ element: ringMarkerEl() }).setLngLat(input.from).addTo(mapInstance),
      );
      if (input.fromLabel) {
        markers.push(
          new Marker({ element: labelMarkerEl(input.fromLabel), anchor: "top" })
            .setLngLat(input.from)
            .addTo(mapInstance),
        );
      }

      if (input.to) {
        const to = input.to;
        markers.push(
          new Marker({ element: planeMarkerEl(bearing(input.from, to)) })
            .setLngLat(input.from)
            .addTo(mapInstance),
          new Marker({ element: ringMarkerEl() }).setLngLat(to).addTo(mapInstance),
        );
        if (input.toLabel) {
          markers.push(
            new Marker({ element: labelMarkerEl(input.toLabel), anchor: "bottom" })
              .setLngLat(to)
              .addTo(mapInstance),
          );
        }
      }
    },
    { immediate: true },
  );

  onUnmounted(clear);
}
