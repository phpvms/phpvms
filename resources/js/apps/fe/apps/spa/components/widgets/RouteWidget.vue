<script setup lang="ts">
import { computed, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useGlobe } from "@/composables/useGlobe";
import { bearing, distanceNm, type LngLat } from "@/lib/geo";

interface Airport {
  icao: string;
  name: string | null;
  lat: number;
  lon: number;
}
interface Route {
  from: Airport | null;
  to: Airport | null;
}

const page = usePage();
const route = computed(() => (page.props.route as Route) ?? { from: null, to: null });
const from = computed<LngLat | null>(() =>
  route.value.from ? [route.value.from.lon, route.value.from.lat] : null,
);
const to = computed<LngLat | null>(() =>
  route.value.to ? [route.value.to.lon, route.value.to.lat] : null,
);

const mapEl = ref<HTMLElement | null>(null);
if (from.value) {
  useGlobe(mapEl, {
    from: from.value,
    to: to.value,
    fromLabel: route.value.from?.icao,
    toLabel: route.value.to?.icao ?? undefined,
  });
}

const hasDest = computed(() => !!to.value);
const trk = computed(() =>
  hasDest.value ? `${Math.round(bearing(from.value!, to.value!))}°` : "—",
);
const dist = computed(() =>
  hasDest.value ? `${Math.round(distanceNm(from.value!, to.value!))} nm` : "—",
);
</script>

<template>
  <div v-if="from" class="route">
    <div ref="mapEl" class="map" />
    <div class="readout">
      <span class="ident"
        >{{ route.from?.icao }}<template v-if="route.to"> → {{ route.to?.icao }}</template></span
      >
      <span class="figs tnum"
        ><span class="k">TRK</span> {{ trk }} · <span class="k">DIST</span> {{ dist }}</span
      >
    </div>
  </div>
  <div v-else class="empty">No position — set a home airport</div>
</template>

<style scoped>
.route {
  display: flex;
  flex-direction: column;
}
.map {
  height: 300px;
  border-radius: var(--pv-radius-md);
  overflow: hidden;
  background: var(--pv-globe-sea);
}
.readout {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-top: 8px;
  font-family: var(--pv-font-mono);
  font-size: 12px;
  color: var(--pv-ink-dim);
}
.ident {
  color: var(--pv-accent);
  font-weight: 500;
}
.figs .k {
  color: var(--pv-ink-faint);
}
.empty {
  font-size: 12px;
  color: var(--pv-ink-dim);
  text-align: center;
  padding: 40px 0;
}
</style>
