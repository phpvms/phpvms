import {
  createMap,
  createLiveFlightMarkers,
  createRoute,
  frameToRoute,
  onUserTakesControl,
} from "@phpvms/map";

const MARKER_LAYER = "live-flights";
const SELECTED_SOURCE = "live-flights-selected";
const SELECTED_RADIUS_PX = 11;
const GLOBE_OVERVIEW_ZOOM = 1;
const FEET_TO_METRES = 0.3048;

/**
 * Resolves once the map's style is loaded; layers cannot be added before that.
 *
 * @param {import("maplibre-gl").Map} map
 */
async function whenLoaded(map) {
  if (!map.loaded()) {
    await map.once("load");
  }
}

/**
 * A CSS colour token (e.g. `--vms-brand`) as rgb(), which maplibre can parse whatever
 * colour syntax the saved scheme uses. Painting one pixel normalises it.
 *
 * @param {string} token
 * @param {string} fallback
 */
function tokenColor(token, fallback) {
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
 *
 * @param {HTMLElement} el
 * @param {{ map: any, initialCenter: { lat: number, lon: number } | null }} config
 * @param {(pirepId: string) => void} onSelect
 */
export function createLiveMap(el, config, onSelect) {
  let disposed = false;
  /** @type {(() => void) | undefined} */
  let destroyMap;
  /** @type {import("maplibre-gl").Map | undefined} */
  let map;
  /** @type {ReturnType<typeof createLiveFlightMarkers> | undefined} */
  let markers;
  /** @type {Promise<any> | undefined} */
  let routePromise;
  /** @type {any} */
  let route;
  /** @type {{ pirepId: string, lat: number, lon: number }[]} */
  let points = [];
  /** @type {any[]} */
  let flown = [];
  /** @type {string | null} */
  let selectedId = null;

  const onClick = (/** @type {any} */ event) => {
    const pirepId = event.features?.[0]?.properties?.pirepId;

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

  async function start() {
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
  function addSelectedRing(/** @type {import("maplibre-gl").Map} */ instance) {
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

  /** @returns {import("geojson").FeatureCollection} */
  function selectedFeatures() {
    const point = points.find((candidate) => candidate.pirepId === selectedId);

    return {
      type: "FeatureCollection",
      features: point
        ? [{ type: "Feature", properties: {}, geometry: { type: "Point", coordinates: [point.lon, point.lat] } }]
        : [],
    };
  }

  function updateSelectedRing() {
    const source = map?.getSource(SELECTED_SOURCE);

    if (source && "setData" in source) {
      /** @type {import("maplibre-gl").GeoJSONSource} */ (source).setData(selectedFeatures());
    }
  }

  // The opening camera runs once, and is skipped when the user has already taken hold of the map.
  function frameInitially(/** @type {import("maplibre-gl").Map} */ instance) {
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
    /** @param {any[]} flights MapLiveFlightData[] */
    setFlights(flights) {
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
     *
     * @param {string | null} pirepId
     */
    select(pirepId) {
      selectedId = pirepId;
      updateSelectedRing();
      flown = [];
      route?.setData(flown);

      if (pirepId) {
        ensureRoute();
      }
    },

    /** @param {any} detail MapPirepDetailData */
    showDetail(detail) {
      // The altitude is the raw ACARS value in feet; the route layer takes metres.
      flown = (detail.flown?.points ?? []).map((/** @type {any} */ point) => ({
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

    destroy() {
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
