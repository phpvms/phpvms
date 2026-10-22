import { onUnmounted, watch, type Ref } from "vue";
import type { Map as MapLibreMapType } from "maplibre-gl";
import { createLiveFlightMarkers, type LiveFlightPoint } from "@phpvms/map";

export type { LiveFlightPoint };

const LAYER_ID = "live-flights";

/**
 * Wires the package's `createLiveFlightMarkers` (design.md D5 — a Tier 0
 * GeoJSON circle layer, wholesale-replaced on every poll) onto `map`, plus
 * click/hover behaviour the package layer doesn't provide on its own:
 * `onSelect(pirepId)` fires on a marker click, and the cursor swaps to a
 * pointer while hovering one. Passing an explicit `id` (`LAYER_ID`) to the
 * package's factory is what makes the layer id predictable here, since
 * `map.on('click', <layerId>, ...)` needs to name it.
 */
export function useLiveFlightMarkers(
  map: Ref<MapLibreMapType | undefined>,
  flights: Ref<LiveFlightPoint[]>,
  onSelect: (pirepId: string) => void,
): void {
  let markers: ReturnType<typeof createLiveFlightMarkers> | undefined;
  let installedOn: MapLibreMapType | undefined;
  let handleClick:
    | ((e: { features?: { properties?: { pirepId?: string } }[] }) => void)
    | undefined;
  let handleEnter: (() => void) | undefined;
  let handleLeave: (() => void) | undefined;

  function uninstall() {
    if (installedOn && handleClick && handleEnter && handleLeave) {
      installedOn.off("click", LAYER_ID, handleClick);
      installedOn.off("mouseenter", LAYER_ID, handleEnter);
      installedOn.off("mouseleave", LAYER_ID, handleLeave);
    }
    markers?.dispose();
    markers = undefined;
    installedOn = undefined;
    handleClick = undefined;
    handleEnter = undefined;
    handleLeave = undefined;
  }

  watch(
    map,
    (mapInstance) => {
      uninstall();
      if (!mapInstance) return;
      markers = createLiveFlightMarkers(mapInstance, { id: LAYER_ID });
      markers.setData(flights.value);

      // Named per install (not the same references across re-installs) so `off()`
      // above always matches the listener actually registered on `installedOn`.
      handleClick = (e) => {
        const pirepId = e.features?.[0]?.properties?.pirepId;
        if (pirepId) onSelect(pirepId);
      };
      handleEnter = () => {
        mapInstance.getCanvas().style.cursor = "pointer";
      };
      handleLeave = () => {
        mapInstance.getCanvas().style.cursor = "";
      };

      mapInstance.on("click", LAYER_ID, handleClick);
      mapInstance.on("mouseenter", LAYER_ID, handleEnter);
      mapInstance.on("mouseleave", LAYER_ID, handleLeave);
      installedOn = mapInstance;
    },
    { immediate: true },
  );

  watch(flights, (currentFlights) => {
    markers?.setData(currentFlights);
  });

  onUnmounted(uninstall);
}
