import {
  createMap,
  createLiveFlightMarkers,
  createRoute,
  frameToRoute,
  onUserTakesControl,
} from "@airside/lib/map";
import type { CreateMapResult, MapConfig, RouteLayer, RoutePoint } from "@airside/lib/map";
import type { FlightDetail, LiveFlight } from "./types";

type MapLibreMap = Extract<CreateMapResult, { ok: true }>["map"];

const MARKER_LAYER = "live-flights";
const SELECTED_SOURCE = "live-flights-selected";
const SELECTED_RADIUS_PX = 11;
const GLOBE_OVERVIEW_ZOOM = 1;
const FEET_TO_METRES = 0.3048;

/**
 * Resolves once the map's style is loaded; layers cannot be added before that.
 */
async function whenLoaded(map: MapLibreMap): Promise<void> {
  if (!map.loaded()) {
    await map.once("load");
  }
}

/**
 * A CSS colour token (e.g. `--vms-brand`) as rgb(), which maplibre can parse whatever
 * colour syntax the saved scheme uses. Painting one pixel normalises it.
 */
function tokenColor(token: string, fallback: string): string {
  const value = getComputedStyle(document.documentElement).getPropertyValue(token).trim();
  const context = document.createElement("canvas").getContext("2d", { willReadFrequently: true });

  if (!value || !context) {
    return fallback;
  }

  context.fillStyle = value;
  context.fillRect(0, 0, 1, 1);
  const [red, green, blue] = context.getImageData(0, 0, 1, 1).data;

  return `rgb(${red}, ${green}, ${blue})`;
}

/**
 * The live map surface (LiveMapCanvas + useLiveFlightMarkers): base map, the
 * flight markers, the opening camera, and the selected flight's flown track.
 */
export function createLiveMap(
  el: HTMLElement,
  config: { map: MapConfig; initialCenter: { lat: number; lon: number } | null },
  onSelect: (pirepId: string) => void,
) {
  let disposed = false;
  let destroyMap: (() => void) | undefined;
  let map: MapLibreMap | undefined;
  let markers: ReturnType<typeof createLiveFlightMarkers> | undefined;
  let routePromise: Promise<RouteLayer> | undefined;
  let route: RouteLayer | undefined;
  let points: { pirepId: string; lat: number; lon: number }[] = [];
  let flown: RoutePoint[] = [];
  let selectedId: string | null = null;

  const onClick = (event: { features?: { properties?: Record<string, unknown> | null }[] }) => {
    const pirepId = event.features?.[0]?.properties?.pirepId as string | undefined;

    if (pirepId) {
      onSelect(pirepId);
    }
  };
  const onEnter = () => {
    if (map) map.getCanvas().style.cursor = "pointer";
  };
  const onLeave = () => {
    if (map) map.getCanvas().style.cursor = "";
  };
  const updateLod = () => route?.updateLod?.();

  async function start(): Promise<void> {
    const result = await createMap(el, {
      config: config.map,
      theme: document.documentElement.classList.contains("dark") ? "dark" : "light",
    });

    if (!result.ok) {
      return;
    }

    if (disposed) {
      result.destroy();

      return;
    }

    destroyMap = result.destroy;
    await whenLoaded(result.map);

    if (disposed) {
      return;
    }

    map = result.map;
    markers = createLiveFlightMarkers(map, { id: MARKER_LAYER });
    markers.setData(points);
    addSelectedRing(map);
    map.on("click", MARKER_LAYER, onClick);
    map.on("mouseenter", MARKER_LAYER, onEnter);
    map.on("mouseleave", MARKER_LAYER, onLeave);

    frameInitially(map);
  }

  // The selected flight's ring: its own source and layer under the markers, in the brand colour.
  function addSelectedRing(instance: MapLibreMap): void {
    const brand = tokenColor("--vms-brand", "#0d6da7");

    instance.addSource(SELECTED_SOURCE, { type: "geojson", data: selectedFeatures() });
    instance.addLayer(
      {
        id: SELECTED_SOURCE,
        type: "circle",
        source: SELECTED_SOURCE,
        paint: {
          "circle-radius": SELECTED_RADIUS_PX,
          "circle-color": brand,
          "circle-opacity": 0.2,
          "circle-stroke-width": 2,
          "circle-stroke-color": brand,
        },
      },
      MARKER_LAYER,
    );
  }

  function selectedFeatures() {
    const point = points.find((candidate) => candidate.pirepId === selectedId);

    return {
      type: "FeatureCollection" as const,
      features: point
        ? [
            {
              type: "Feature" as const,
              properties: {},
              geometry: { type: "Point" as const, coordinates: [point.lon, point.lat] },
            },
          ]
        : [],
    };
  }

  function updateSelectedRing() {
    const source = map?.getSource(SELECTED_SOURCE);

    if (source && "setData" in source) {
      (source as { setData(data: ReturnType<typeof selectedFeatures>): void }).setData(selectedFeatures());
    }
  }

  // The opening camera runs once, and is skipped when the user has already taken hold of the map.
  function frameInitially(instance: MapLibreMap): void {
    if (!config.initialCenter) {
      return;
    }

    let userTookControl = false;
    const stopGuard = onUserTakesControl(instance, () => {
      userTookControl = true;
    });

    if (!userTookControl) {
      instance.jumpTo({
        center: [config.initialCenter.lon, config.initialCenter.lat],
        zoom: GLOBE_OVERVIEW_ZOOM,
        pitch: 0,
      });
    }

    stopGuard();
  }

  // The flown track is a Tier 1 route; its three.js code loads on the first selection only.
  async function ensureRoute() {
    if (!map) {
      return undefined;
    }

    if (!routePromise) {
      const instance = map;

      routePromise = createRoute(instance, 1, { id: "live-map-flown" }).then((created) => {
        if (disposed || map !== instance) {
          created.dispose();

          return created;
        }

        route = created;
        instance.on("zoomend", updateLod);
        instance.on("moveend", updateLod);
        route.setData(flown);

        return created;
      });
    }

    return routePromise;
  }

  start();

  return {
    setFlights(flights: LiveFlight[]): void {
      points = flights.map((flight) => ({
        pirepId: flight.pirepId,
        lat: flight.position.lat,
        lon: flight.position.lon,
      }));
      markers?.setData(points);
      updateSelectedRing();
    },

    /**
     * A flight was selected (or the selection cleared, with null): ring it, clear the
     * previous track, and load the track layer.
     */
    select(pirepId: string | null): void {
      selectedId = pirepId;
      updateSelectedRing();
      flown = [];
      route?.setData(flown);

      if (pirepId) {
        ensureRoute();
      }
    },

    showDetail(detail: FlightDetail): void {
      // The altitude is the raw ACARS value in feet; the route layer takes metres.
      flown = (detail.flown?.points ?? []).map((point) => ({
        lat: point.lat,
        lon: point.lon,
        altitude: (point.altitude ?? 0) * FEET_TO_METRES,
        phase: point.phase ?? undefined,
      }));

      ensureRoute()?.then(() => {
        route?.setData(flown);

        if (map && flown.length > 0) {
          frameToRoute(map, flown, { pitch: 45 });
        }
      });
    },

    destroy(): void {
      disposed = true;

      if (map) {
        map.off("click", MARKER_LAYER, onClick);
        map.off("mouseenter", MARKER_LAYER, onEnter);
        map.off("mouseleave", MARKER_LAYER, onLeave);
        map.off("zoomend", updateLod);
        map.off("moveend", updateLod);
      }

      route?.dispose();

      if (map?.getLayer(SELECTED_SOURCE)) map.removeLayer(SELECTED_SOURCE);
      if (map?.getSource(SELECTED_SOURCE)) map.removeSource(SELECTED_SOURCE);

      markers?.dispose();
      destroyMap?.();
    },
  };
}
