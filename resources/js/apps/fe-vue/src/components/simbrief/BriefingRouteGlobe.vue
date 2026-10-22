<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from "vue";
import { useMap, useRoute } from "@phpvms/map/adapters/vue";
import { createRouteWaypoints, frameToRoute, LEG_COLORS } from "@phpvms/map";
import type { RouteLegKind, RouteWaypoint, RouteWaypointLayer } from "@phpvms/map";
import type { RoutePoint } from "@phpvms/map";
import { useMapContext } from "@/shared/lib/useMapContext";
import { useRouteMarkers } from "@/shared/lib/useRouteMarkers";
import { greatCircle, type LngLat } from "@/shared/lib/geo";

/**
 * Briefing route globe (maplibre-map-platform tasks.md 7.5) — Tier 0 only
 * (design.md D13: the briefing OVERVIEW is Tier 0; a Tier 1 "briefing detail
 * view" is listed as a possible future addition, not built here). Plots the
 * navlog's fixes in order when one exists; falls back to a direct
 * origin-destination line when it doesn't (map-surfaces spec, "Briefing
 * without a navlog"); shows the existing missing-coordinates message
 * (map-surfaces spec, "Missing airport coordinates") when neither is
 * possible.
 */
const props = defineProps<{
  departure: App.Http.Data.AirportPointData | null;
  arrival: App.Http.Data.AirportPointData | null;
  plannedFixes: App.Http.Data.MapPlannedFixData[];
}>();

const departurePoint = computed<LngLat | null>(() =>
  props.departure?.lat != null && props.departure.lon != null
    ? [props.departure.lon, props.departure.lat]
    : null,
);
const arrivalPoint = computed<LngLat | null>(() =>
  props.arrival?.lat != null && props.arrival.lon != null
    ? [props.arrival.lon, props.arrival.lat]
    : null,
);

/**
 * Two points are the same place if they agree to ~1 km. Used to decide whether
 * an airport endpoint is already present in the navlog.
 */
const SAME_PLACE_DEGREES = 0.01;

function isSamePlace(point: { lat: number; lon: number }, airport: LngLat): boolean {
  return (
    Math.abs(point.lon - airport[0]) < SAME_PLACE_DEGREES &&
    Math.abs(point.lat - airport[1]) < SAME_PLACE_DEGREES
  );
}

type ClassifiedFix = { lat: number; lon: number; ident: string | null; kind: RouteLegKind };

/**
 * Group the navlog into legs and label each one, porting acars's rule
 * (`RouteGeoJson.SplitIntoLegs`): consecutive fixes sharing a `viaAirway` form
 * one leg, every leg is `enroute` by default, then the FIRST leg becomes `sid`
 * and the LAST becomes `star` if any of their fixes carries the SID/STAR flag.
 * acars decides on the airway-run boundary rather than per fix because SimBrief
 * sets `is_sid_star` inconsistently across the fixes of one procedure.
 */
function classifyFixes(fixes: App.Http.Data.MapPlannedFixData[]): ClassifiedFix[] {
  if (fixes.length === 0) return [];

  const runs: App.Http.Data.MapPlannedFixData[][] = [];
  for (const fix of fixes) {
    const current = runs.at(-1);
    if (current && current[0].viaAirway === fix.viaAirway) current.push(fix);
    else runs.push([fix]);
  }

  const kinds: RouteLegKind[] = runs.map(() => "enroute");
  if (runs[0].some((fix) => fix.isSidStar)) kinds[0] = "sid";
  // Guarded on length: with one leg the whole route would otherwise be a STAR.
  if (runs.length > 1 && runs.at(-1)?.some((fix) => fix.isSidStar))
    kinds[kinds.length - 1] = "star";

  return runs.flatMap((run, index) =>
    run.map((fix) => ({ lat: fix.lat, lon: fix.lon, ident: fix.ident, kind: kinds[index] })),
  );
}

/**
 * The navlog's classified fixes, bounded by the airports.
 *
 * SimBrief's navlog is not symmetric about the airports: in the reference OFP
 * it ENDS on the destination (`OMDB`, type `apt`) but STARTS on the first climb
 * waypoint (`BOMUP` at 3400 ft), with the origin absent — acars documents the
 * same asymmetry and prepends the origin for exactly this reason. So each end
 * is added only when the navlog does not already reach it; appending
 * unconditionally would double the destination on a normal OFP.
 */
const routeFixes = computed<ClassifiedFix[]>(() => {
  const fixes = classifyFixes(props.plannedFixes);
  if (fixes.length === 0) return fixes;

  const departure = departurePoint.value;
  if (departure && !isSamePlace(fixes[0], departure)) {
    fixes.unshift({
      lat: departure[1],
      lon: departure[0],
      ident: props.departure?.icao ?? null,
      kind: fixes[0].kind,
    });
  }

  const arrival = arrivalPoint.value;
  const last = fixes.at(-1);
  if (arrival && last && !isSamePlace(last, arrival)) {
    fixes.push({
      lat: arrival[1],
      lon: arrival[0],
      ident: props.arrival?.icao ?? null,
      kind: last.kind,
    });
  }

  return fixes;
});

/** `phase` is what the Tier 0 line matches on to colour each leg run. */
const fixPoints = computed<RoutePoint[]>(() =>
  routeFixes.value.map((fix) => ({ lat: fix.lat, lon: fix.lon, altitude: 0, phase: fix.kind })),
);

/** No navlog: a densified great circle between the airports so it still curves under the globe projection. */
const directPoints = computed<RoutePoint[]>(() =>
  departurePoint.value && arrivalPoint.value
    ? greatCircle(departurePoint.value, arrivalPoint.value).map(([lon, lat]) => ({
        lat,
        lon,
        altitude: 0,
      }))
    : [],
);

const points = computed<RoutePoint[]>(() =>
  fixPoints.value.length > 0 ? fixPoints.value : directPoints.value,
);

/** Dots and ident labels. Only the navlog case has fixes worth labelling. */
const waypoints = computed<RouteWaypoint[]>(() =>
  fixPoints.value.length > 0
    ? routeFixes.value.map((fix) => ({
        lat: fix.lat,
        lon: fix.lon,
        ident: fix.ident,
        kind: fix.kind,
      }))
    : [],
);

/** Neither a navlog nor two airport coordinate pairs to fall back to — nothing plottable. */
const unavailable = computed(() => points.value.length === 0);

const mapEl = ref<HTMLElement | undefined>(undefined);
const { config, theme } = useMapContext();
// `terrain: false` — this is a small fixed overview that is never tilted by hand, so the DEM's
// second tile stream would be paid for with no benefit. The live map and the PIREP route, which
// you can actually tilt, keep it on.
const { map } = useMap(mapEl, { config: config.value, theme: theme.value, terrain: false });

useRoute(map, 0, points, { phaseColors: LEG_COLORS });
useRouteMarkers(
  map,
  computed(() =>
    departurePoint.value
      ? {
          from: departurePoint.value,
          to: arrivalPoint.value,
          fromLabel: props.departure?.icao,
          toLabel: props.arrival?.icao ?? undefined,
        }
      : null,
  ),
);

/**
 * The waypoint layer is created once the map is exposed (which `useMap` only
 * does after the style has loaded, so `addSource`/`addLayer` are safe) and torn
 * down whenever the map goes away, so it never outlives the map it drew on.
 */
let waypointLayer: RouteWaypointLayer | undefined;

watch(
  [map, waypoints],
  ([mapInstance, currentWaypoints]) => {
    if (!mapInstance) {
      waypointLayer = undefined;
      return;
    }
    waypointLayer ??= createRouteWaypoints(mapInstance);
    waypointLayer.setData(currentWaypoints);
  },
  { immediate: true },
);

onUnmounted(() => {
  waypointLayer?.dispose();
  waypointLayer = undefined;
});

watch(
  [map, points],
  ([mapInstance, currentPoints]) => {
    if (!mapInstance || currentPoints.length === 0) return;
    frameToRoute(mapInstance, currentPoints, { pitch: 30 });
  },
  { immediate: true },
);
</script>

<template>
  <div class="briefing-globe">
    <div v-if="!unavailable" ref="mapEl" class="map" />
    <div v-else class="map-empty" role="status">
      Route map unavailable because airport coordinates are missing.
    </div>
  </div>
</template>

<style scoped>
@layer components {
  .briefing-globe {
    min-width: 0;
  }
  .map {
    height: 280px;
    border-radius: var(--pv-radius-md);
    overflow: hidden;
    background: var(--pv-panel-inset);
  }
  .map-empty {
    display: grid;
    min-height: 180px;
    place-items: center;
    border: 1px dashed var(--pv-line);
    border-radius: var(--pv-radius-md);
    background: var(--pv-panel-inset);
    color: var(--pv-ink-dim);
    padding: 24px;
    text-align: center;
  }
}
</style>
