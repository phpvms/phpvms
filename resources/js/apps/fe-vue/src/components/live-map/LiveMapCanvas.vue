<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useMap, useRoute } from "@phpvms/map/adapters/vue";
import { frameToRoute, onUserTakesControl } from "@phpvms/map";
import type { RoutePoint } from "@phpvms/map";
import { useMapContext } from "@/shared/lib/useMapContext";
import { useLiveFlightMarkers } from "./useLiveFlightMarkers";

/**
 * The map surface: base map, live-flight markers (design.md D4/D5,
 * wholesale-replaced whenever `flights` changes), the initial camera jump,
 * and the selected flight's Tier 1 flown track (design.md D13's table lists
 * "live map selected flight" as Tier 1 explicitly). All package wiring for
 * the live map lives here — the page just feeds it data and listens for
 * `select`.
 */
const props = defineProps<{
  flights: App.Http.Data.MapLiveFlightData[];
  detail: App.Http.Data.MapPirepDetailData | null;
  selectedPirepId: string | null;
  /** Resolved server-side (`LiveMapController::resolveInitialCenter()`) — operator setting, else the first hub airport, else `null` (package default). */
  initialCenter: { lat: number; lon: number } | null;
}>();

const emit = defineEmits<{ select: [pirepId: string] }>();

const markerPoints = computed(() =>
  props.flights.map((f) => ({ pirepId: f.pirepId, lat: f.position.lat, lon: f.position.lon })),
);

const mapEl = ref<HTMLElement | undefined>(undefined);
const { config, theme } = useMapContext();
const { map } = useMap(mapEl, { config: config.value, theme: theme.value });

useLiveFlightMarkers(map, markerPoints, (pirepId) => emit("select", pirepId));

/**
 * Initial camera: the resolved centre, at a globe-scale zoom, once, when the
 * map first becomes available. NOT re-run on poll ticks — `framedInitially`
 * flips permanently on the first call, so a later `flights` update can never
 * re-trigger this block, and `onUserTakesControl` additionally skips the
 * jump outright if the user has already taken hold of the camera before
 * this fires (camera.ts's own guard against yanking the view back
 * mid-interaction).
 */
const GLOBE_OVERVIEW_ZOOM = 1;
let framedInitially = false;
watch(
  map,
  (mapInstance) => {
    if (!mapInstance || framedInitially || !props.initialCenter) return;
    framedInitially = true;
    let userTookControl = false;
    const stopGuard = onUserTakesControl(mapInstance, () => {
      userTookControl = true;
    });
    if (!userTookControl) {
      mapInstance.jumpTo({
        center: [props.initialCenter.lon, props.initialCenter.lat],
        zoom: GLOBE_OVERVIEW_ZOOM,
        pitch: 0,
      });
    }
    stopGuard();
  },
  { immediate: true },
);

const flownPoints = computed<RoutePoint[]>(() =>
  (props.detail?.flown.points ?? []).map((p) => ({
    lat: p.lat,
    lon: p.lon,
    // MapTrackPointData.altitude is the raw ACARS altitude_msl value — feet, not
    // metres (confirmed in tests/Feature/MapApiTest.php: an altitude_msl of
    // 33000 round-trips as `position.altitude === 33000`, unconverted). The
    // package's RoutePoint.altitude is metres (matches getMatrixForModel), so
    // this is the one place on this page that has to convert.
    altitude: (p.altitude ?? 0) * 0.3048,
    phase: p.phase ?? undefined,
  })),
);

// Tier 1 only mounts once a flight has actually been selected (a `computed`
// that gates `map` behind that flag) — `useRoute` creates its layer, and
// dynamic-imports `route-line.ts` (three.js), as soon as the ref it's given
// resolves to a live map instance, regardless of whether `points` is empty.
// Passing `map` directly would fetch Tier 1 on page load for every visitor,
// not just the ones who click a flight. Gated on `selectedPirepId` (fires
// the instant a flight is clicked) rather than `detail` (arrives later, once
// the fetch resolves) so the three.js import starts in parallel with the
// detail fetch instead of waiting for it.
const hasSelectedFlight = ref(false);
watch(
  () => props.selectedPirepId,
  (id) => {
    if (id) hasSelectedFlight.value = true;
  },
);
const tier1Map = computed(() => (hasSelectedFlight.value ? map.value : undefined));
useRoute(tier1Map, 1, flownPoints, { id: "live-map-flown" });

watch(
  () => props.detail,
  (current) => {
    const mapInstance = map.value;
    if (!mapInstance || !current || flownPoints.value.length === 0) return;
    frameToRoute(mapInstance, flownPoints.value, { pitch: 45 });
  },
);
</script>

<template>
  <div ref="mapEl" class="map" />
</template>

<style scoped>
@layer components {
  .map {
    height: 70vh;
    min-height: 22rem;
    background: var(--pv-panel-inset);
  }
}
</style>
