<script setup lang="ts">
import { ref, computed, watch } from "vue";
import { useMap, useRoute } from "@phpvms/map/adapters/vue";
import { frameToRoute } from "@phpvms/map";
import type { RoutePoint } from "@phpvms/map";
import { useMapContext } from "@/shared/lib/useMapContext";
import { useRouteMarkers } from "@/shared/lib/useRouteMarkers";
import { greatCircle, bearing, distanceNm, type LngLat } from "@/shared/lib/geo";

/**
 * Nav Display — Tier 0 (design.md D13: no three.js) globe over the shared
 * `@phpvms/map` package, framed like an EFIS ND (mono header + bottom
 * readout). Colors are driven by `--pv-globe-*` / `--pv-accent` tokens.
 * Falls back to origin-only when no destination is known.
 *
 * Previously drew its own tile-free globe via `useGlobe` (Natural Earth
 * topojson land + DOM markers, no basemap). That helper is retired
 * (maplibre-map-platform design.md D12) — the configured basemap now renders
 * under the same great-circle line, and `useRouteMarkers` draws the same
 * origin/destination ring markers + plane glyph (`.mk-*` classes below) it
 * used to, so the visuals carry over.
 *
 * @unused Not yet wired to a page slot. The dashboard RouteWidget embeds the
 * package directly. Retained as a reusable standalone component for future
 * page or addon use.
 */
const props = defineProps<{
  from: LngLat;
  to?: LngLat | null;
  fromIcao: string;
  toIcao?: string | null;
  fl?: string;
}>();

const mapEl = ref<HTMLElement | undefined>(undefined);
const { config, theme } = useMapContext();
const { map } = useMap(mapEl, { config: config.value, theme: theme.value });

/** Densified great circle so it curves smoothly under the globe projection — a raw 2-point line would render as a mercator-straight chord. */
const points = computed<RoutePoint[]>(() =>
  props.to
    ? greatCircle(props.from, props.to).map(([lon, lat]) => ({ lat, lon, altitude: 0 }))
    : [],
);

useRoute(map, 0, points);
useRouteMarkers(
  map,
  computed(() => ({
    from: props.from,
    to: props.to ?? null,
    fromLabel: props.fromIcao,
    toLabel: props.toIcao ?? undefined,
  })),
);

watch(
  map,
  (mapInstance) => {
    if (!mapInstance) return;
    frameToRoute(
      mapInstance,
      points.value.length ? points.value : [{ lat: props.from[1], lon: props.from[0] }],
      {
        pitch: 0,
      },
    );
  },
  { immediate: true },
);

const hasRoute = computed(() => !!props.to);
const trk = computed(() =>
  hasRoute.value
    ? `TRK ${String(Math.round(bearing(props.from, props.to as LngLat))).padStart(3, "0")}°`
    : "TRK ---°",
);
const dist = computed(() =>
  hasRoute.value
    ? `DIST ${Math.round(distanceNm(props.from, props.to as LngLat))}NM`
    : "DIST ----NM",
);
const ete = computed(() => {
  if (!hasRoute.value) return "ETE --:--";
  // rough ETE at 460kt ground speed
  const hrs = distanceNm(props.from, props.to as LngLat) / 460;
  const h = Math.floor(hrs);
  const m = Math.round((hrs - h) * 60);
  return `ETE ${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
});
</script>

<template>
  <div
    class="nd"
    role="img"
    :aria-label="`Nav display, route ${fromIcao} to ${toIcao ?? 'unknown'}`"
  >
    <div class="nd-h">
      <span class="t">ND · MAPLIBRE · GLOBE</span>
      <span class="m"
        >{{ fromIcao }} <template v-if="toIcao">▸ {{ toIcao }}</template></span
      >
    </div>
    <div ref="mapEl" class="map" />
    <div class="readout">
      <span class="mag">{{ trk }}</span>
      <span>{{ dist }}</span>
      <span>{{ ete }}</span>
      <span>{{ fl ?? "FL380" }}</span>
    </div>
  </div>
</template>

<style scoped>
.nd {
  position: relative;
  overflow: hidden;
  background: var(--pv-panel);
  border: 1px solid var(--pv-line);
  border-radius: var(--pv-radius-md);
  box-shadow: var(--pv-shadow-panel);
}
.nd-h {
  display: flex;
  justify-content: space-between;
  padding: 10px 16px;
  border-bottom: 1px solid var(--pv-line);
  position: relative;
  z-index: 2;
}
.nd-h .t {
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--pv-ink-dim);
}
.nd-h .m {
  font-family: var(--pv-font-mono);
  font-size: calc(9px * var(--pv-type-scale));
  letter-spacing: 0.12em;
  color: var(--pv-accent);
}
.map {
  height: 420px;
  background: var(--pv-panel);
}
.readout {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  justify-content: space-between;
  z-index: 2;
  padding: 8px 16px;
  font-family: var(--pv-font-mono);
  font-size: calc(8px * var(--pv-type-scale));
  letter-spacing: 0.1em;
  color: var(--pv-ink);
  background: linear-gradient(
    transparent,
    color-mix(in srgb, var(--pv-panel) 85%, transparent) 60%
  );
  pointer-events: none;
}
.readout .mag {
  color: var(--pv-accent);
}
</style>
