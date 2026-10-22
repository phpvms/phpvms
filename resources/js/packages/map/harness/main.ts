import maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import { createRouteLine, DEFAULT_LOD_THRESHOLD_ZOOM } from "../src/route-line.ts";
import { fixtureLongHaulRoute } from "./fixture-route.ts";
import { findColorRuns, summarizeWidths } from "./measure.ts";

/**
 * Standalone harness for `route-line.ts` — run with
 * `pnpm --filter @phpvms/map dev`. Used for:
 * - the gated `Line2` spike (tasks.md 2.0, superseded once `route-line.ts`
 *   existed — this now drives the REAL module, not the throwaway spike);
 * - measuring the LOD merge/cull switch threshold (tasks.md 2.5);
 * - the visual check against acars on a real track (tasks.md 2.9).
 *
 * Key-free Carto Voyager basemap per design.md D8's curated list.
 */

const BASEMAP_STYLE = "https://basemaps.cartocdn.com/gl/voyager-nolabels-gl-style/style.json";
const PIXEL_RATIO = Math.min(window.devicePixelRatio || 1, 2); // design.md D16

const PHASE_COLORS = { climb: "#f59e0b", cruise: "#22c55e", descent: "#38bdf8" };

const route = fixtureLongHaulRoute();
const cruisePoint = route[Math.floor(route.length / 2)];
const climbEnd = route.find((p) => p.phase === "cruise")!;
const routeMidLon = (route[0].lon + route.at(-1)!.lon) / 2;
const routeMidLat = (route[0].lat + route.at(-1)!.lat) / 2;

const map = new maplibregl.Map({
  container: "map",
  style: BASEMAP_STYLE,
  center: [routeMidLon, routeMidLat],
  zoom: 3,
  pitch: 45,
  canvasContextAttributes: { antialias: true },
});
map.setPixelRatio(PIXEL_RATIO);

const status = document.getElementById("status")!;
const resultsEl = document.getElementById("results")!;

map.on("load", () => {
  map.setProjection({ type: "globe" });

  // `createRouteLine` self-registers via `map.addLayer` internally now — no separate call here.
  const routeLine = createRouteLine(map, { phaseColors: PHASE_COLORS, defaultColor: "#e879f9" });
  routeLine.setData(route);
  map.on("zoomend", () => routeLine.updateLod());
  map.on("moveend", () => routeLine.updateLod());

  // Debug hook for the harness's own headless verification pass — not part of the package API.
  (window as unknown as { __harness: unknown }).__harness = {
    map,
    routeLine,
    findColorRuns,
    route,
    cruisePoint,
    climbEnd,
  };

  status.textContent = `map ready — click a pose, then "Measure". LOD threshold zoom: ${DEFAULT_LOD_THRESHOLD_ZOOM}`;

  const poseWhole = () => {
    map.flyTo({
      center: [routeMidLon, routeMidLat],
      zoom: 2.6,
      pitch: 50,
      bearing: 0,
      duration: 800,
    });
    status.textContent = "pose: whole route framed";
  };
  // zoom 9.5/pitch 79 is the closest-in, steepest pose this fixture's naive ground-centred
  // `flyTo` keeps the elevated (10.7 km) line inside the frustum at — see the tasks.md 2.0
  // report for why the literal acars-style zoom ~13/pitch ~82 pose loses the line entirely.
  const poseLowCruise = () => {
    map.flyTo({
      center: [cruisePoint.lon, cruisePoint.lat],
      zoom: 9.5,
      pitch: 79,
      bearing: 55,
      duration: 800,
    });
    status.textContent = "pose: low, near cruise-altitude segment";
  };
  const poseLowClimb = () => {
    map.flyTo({
      center: [climbEnd.lon, climbEnd.lat],
      zoom: 9.5,
      pitch: 79,
      bearing: 55,
      duration: 800,
    });
    status.textContent = "pose: low, near climb→cruise phase boundary";
  };

  document.getElementById("pose-whole")!.addEventListener("click", poseWhole);
  document.getElementById("pose-low-cruise")!.addEventListener("click", poseLowCruise);
  document.getElementById("pose-low-climb")!.addEventListener("click", poseLowClimb);

  document.getElementById("zoom-out")!.addEventListener("click", () => {
    map.easeTo({ zoom: map.getZoom() - 0.5, duration: 300 });
  });
  document.getElementById("zoom-in")!.addEventListener("click", () => {
    map.easeTo({ zoom: map.getZoom() + 0.5, duration: 300 });
  });

  map.on("zoom", () => {
    status.textContent = `zoom ${map.getZoom().toFixed(2)} — chunks: ${routeLine.chunkCount()} (LOD threshold: ${DEFAULT_LOD_THRESHOLD_ZOOM})`;
  });

  document.getElementById("measure")!.addEventListener("click", () => {
    const doMeasure = () => {
      const canvas = map.getCanvas();
      const gl = canvas.getContext("webgl2") as WebGL2RenderingContext | null;
      if (!gl) {
        resultsEl.textContent = "no webgl2 context available for readback";
        return;
      }
      const w = gl.drawingBufferWidth;
      const h = gl.drawingBufferHeight;
      const pixels = new Uint8Array(w * h * 4);
      gl.readPixels(0, 0, w, h, gl.RGBA, gl.UNSIGNED_BYTE, pixels);
      const runs = findColorRuns(pixels, w, h, PHASE_COLORS);
      resultsEl.textContent = summarize(runs);
    };
    map.once("idle", () => requestAnimationFrame(doMeasure));
    map.triggerRepaint();
  });

  poseWhole();
});

function summarize(runs: ReturnType<typeof findColorRuns>): string {
  const summaries = summarizeWidths(runs);
  if (summaries.length === 0) {
    return "no matching-colour pixels found — route not on screen at this pose, or colours drifted";
  }
  const lines = summaries.map(
    (s) => `${s.phase}: n=${s.n} min=${s.min}px median=${s.median}px max=${s.max}px`,
  );
  return lines.join("\n");
}
