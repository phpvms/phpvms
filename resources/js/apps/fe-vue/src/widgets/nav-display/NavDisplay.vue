<script setup lang="ts">
import { ref, computed } from "vue";
import { useGlobe } from "@/shared/lib/useGlobe";
import { bearing, distanceNm, type LngLat } from "@/shared/lib/geo";

/**
 * Nav Display — a tile-free MapLibre globe with a blue great-circle route,
 * framed like an EFIS ND (mono header + bottom readout). Colors are driven by
 * `--pv-globe-*` / `--pv-accent` tokens. Falls back to origin-only when no
 * destination is known.
 *
 * @unused Not yet wired to a page slot. The dashboard RouteWidget embeds the
 * globe via @/shared/lib/useGlobe directly. Retained as a reusable standalone
 * component for future page or addon use.
 */
const props = defineProps<{
  from: LngLat;
  to?: LngLat | null;
  fromIcao: string;
  toIcao?: string | null;
  fl?: string;
}>();

const mapEl = ref<HTMLElement | null>(null);

useGlobe(mapEl, {
  from: props.from,
  to: props.to ?? null,
  fromLabel: props.fromIcao,
  toLabel: props.toIcao ?? undefined,
});

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

<!-- Global: imperative MapLibre DOM markers live outside scoped-style reach. -->
<style>
.mk-apt {
  font-family: var(--pv-font-mono);
  font-size: calc(11px * var(--pv-type-scale));
  color: var(--pv-accent);
  white-space: nowrap;
  transform: translateY(-14px);
  pointer-events: none;
}
.mk-ring {
  width: 12px;
  height: 12px;
  border: 1.6px solid var(--pv-accent);
  border-radius: 50%;
  position: relative;
}
.mk-ring::after {
  content: "";
  position: absolute;
  inset: 3px;
  background: var(--pv-accent);
  border-radius: 50%;
}
.mk-plane {
  color: var(--pv-accent);
}
.maplibregl-ctrl-attrib {
  font-size: calc(8px * var(--pv-type-scale));
  opacity: 0.5;
}
</style>
