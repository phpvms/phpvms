import type { Map as MapLibreMapType } from "maplibre-gl";
import * as THREE from "three";
import { Line2 } from "three/examples/jsm/lines/Line2.js";
import { LineGeometry } from "three/examples/jsm/lines/LineGeometry.js";
import { LineMaterial } from "three/examples/jsm/lines/LineMaterial.js";
import {
  type AnchoredChunkHandle,
  type AnchoredCustomLayer,
  createAnchoredCustomLayer,
  localPositionFn,
} from "./anchored-scene.ts";
import { chunkLine } from "./chunking.ts";
import { hexToLinearRgb } from "./color.ts";
import { applyDecomposedMvp } from "./line2-camera.ts";
import { type LodSelection, selectChunksForLod } from "./lod.ts";
import type { RoutePoint, ScenePosition } from "./types.ts";

/**
 * The route line content type (design.md D3 — verdict: `Line2` holds, see
 * tasks.md 2.0's report). Built on `anchored-scene.ts`'s generic substrate;
 * everything here is specific to drawing a phase-coloured, constant-pixel-
 * width line with `Line2`/`LineGeometry`/`LineMaterial`.
 *
 * One geometry per anchored chunk, one shared `LineMaterial` for the whole
 * layer (`worldUnits: false` for constant CSS-pixel width with zero per-frame
 * CPU, `vertexColors: true` so phase colours live in one geometry per chunk
 * rather than one drawable per phase run, `depthTest`/`depthWrite: false` so
 * the route is never occluded by the basemap or another 3D layer).
 */

/** The route magenta `../acars/.../altitude-scene.ts` documents as its default. */
export const DEFAULT_ROUTE_COLOR = "#e879f9";
const DEFAULT_LINEWIDTH_PX = 4;

/**
 * The camera-framed-to-whole-route ↔ zoomed-in switch (design.md D14).
 *
 * MEASURED, not guessed, against the package's own JFK→LHR harness fixture
 * (~5,540 km, 207 chunks) at a 1280×577 viewport: zoom 3 shows the whole
 * route with margin on both sides; by zoom 4 it is pressed against the
 * canvas edges; by zoom 5 only a fraction is visible. 4 is where the
 * transition happens for THIS route length and THIS viewport — a shorter
 * route or a larger viewport shifts it. This is one measurement, not a
 * general formula; a real implementation should likely derive the threshold
 * from the route's own angular extent rather than hardcode one number for
 * every route length (left for `map-surfaces` camera-framing work, task 3.2,
 * to settle — see the tasks.md 2.5 report for the full method).
 */
export const DEFAULT_LOD_THRESHOLD_ZOOM = 4;

export type PhaseColorMap = Record<string, string>;

let anonymousRouteLineCount = 0;

export type RouteLineOptions = {
  /** Colour for a point whose `phase` is missing or not present in `phaseColors`. */
  defaultColor?: string;
  phaseColors?: PhaseColorMap;
  lineWidthPx?: number;
  /** design.md D14's merge/cull switch point. Defaults to `DEFAULT_LOD_THRESHOLD_ZOOM`. */
  lodThresholdZoom?: number;
  /**
   * MapLibre layer id. A caller drawing more than one route on the same map
   * (e.g. flown + planned) MUST give each its own id — `map.addLayer` throws
   * "Layer already exists" on a collision, which a hardcoded id here would
   * guarantee for a second instance.
   */
  id?: string;
};

export type RouteLine = AnchoredCustomLayer<RoutePoint> & {
  /**
   * Wholesale rebuild from a full point set (design.md D4 — `setData`-style,
   * no incremental `push`; no phpVMS surface receives a real-time position
   * stream). An empty array disposes the previous geometry and draws nothing.
   * Resets to the merged/culled state appropriate for the current camera —
   * callers do not need to follow with `updateLod()`.
   */
  setData(points: RoutePoint[]): void;
  /**
   * Re-evaluate chunk LOD (design.md D14) against the map's current zoom and
   * bounds, and rebuild only if the selection actually changed. Callers wire
   * this to `map.on('zoomend'|'moveend', ...)` — LOD is camera-driven, not a
   * per-frame recomputation (`chunkLine` never changes; only which of its
   * chunks are handed to the renderer does).
   */
  updateLod(): void;
  /** Release the shared material and every chunk's geometry. Call before dropping the layer. */
  dispose(): void;
};

/**
 * The colour a track point renders in. A point with no `phase`, or a `phase`
 * absent from `phaseColors`, falls back to `defaultColor` rather than
 * failing — map-rendering spec, "Unknown phase".
 */
export function colorForPoint(
  point: RoutePoint,
  phaseColors: PhaseColorMap,
  defaultColor: string,
): string {
  if (point.phase === undefined) return defaultColor;
  return phaseColors[point.phase] ?? defaultColor;
}

/** Build one chunk's `LineGeometry`: anchor-relative positions plus one colour per vertex. */
export function buildChunkGeometry(
  anchor: ScenePosition,
  points: RoutePoint[],
  phaseColors: PhaseColorMap,
  defaultColor: string,
  isGlobe: boolean,
): LineGeometry {
  const local = points.map(localPositionFn(anchor, isGlobe));
  const geometry = new LineGeometry();
  geometry.setPositions(local.flatMap((p) => [p.x, p.y, p.z]));
  geometry.setColors(
    points.flatMap((point) => hexToLinearRgb(colorForPoint(point, phaseColors, defaultColor))),
  );
  return geometry;
}

/**
 * A key that changes if and only if `selection.chunks` is actually a different set than the
 * last one applied — `applyLod`'s guard against a no-op rebuild on e.g. two `moveend`s in a row
 * without crossing the LOD threshold.
 *
 * Confirmed a real bug (codex review, verified): the previous key was
 * `${merged}:${chunks.length}`, which is identical for two DIFFERENT sets of the same size —
 * panning A/B/C -> D/E/F while zoomed in (same culled chunk count, different actual chunks) was
 * silently treated as a no-op and kept the stale geometry on screen.
 */
export function selectionKey(
  fineChunks: RoutePoint[][],
  selection: LodSelection<RoutePoint>,
): string {
  if (selection.merged) {
    // The merged branch's output depends only on `fineChunks` and the merge group size (both
    // fixed once `setData` runs), never on `bounds` (`lod.ts`'s `mergeChunks` does not take
    // bounds) — so chunk count alone is already a sufficient identity here; unlike the unmerged
    // branch, panning while merged cannot select a different set.
    return `merged:${selection.chunks.length}`;
  }
  // Reference equality: `selectChunksForLod`'s non-merged branch filters `fineChunks` in place
  // (`lod.ts`'s `anchorInBounds` filter never rebuilds new arrays), so each selected chunk IS
  // one of `fineChunks`'s own elements — `indexOf` recovers WHICH ones were selected.
  return `unmerged:${selection.chunks.map((chunk) => fineChunks.indexOf(chunk)).join(",")}`;
}

export function createRouteLine(map: MapLibreMapType, options: RouteLineOptions = {}): RouteLine {
  const phaseColors = options.phaseColors ?? {};
  const defaultColor = options.defaultColor ?? DEFAULT_ROUTE_COLOR;

  // ONE material, shared by every chunk: linewidth is CSS pixels (`worldUnits: false`), so a
  // single shared instance is what makes every chunk agree on the same on-screen width.
  const material = new LineMaterial({
    linewidth: options.lineWidthPx ?? DEFAULT_LINEWIDTH_PX,
    worldUnits: false,
    vertexColors: true,
    depthTest: false,
    depthWrite: false,
  });
  // Reused across chunks within a frame — `applyDecomposedMvp` repoints it per chunk, matching
  // the pattern proven in the tasks.md 2.0 spike (one `THREE.Camera`, mutated per draw call).
  const camera = new THREE.Camera();

  function buildChunk(
    anchor: ScenePosition,
    points: RoutePoint[],
    isGlobe: boolean,
  ): AnchoredChunkHandle {
    const geometry = buildChunkGeometry(anchor, points, phaseColors, defaultColor, isGlobe);
    const line = new Line2(geometry, material);
    line.computeLineDistances();
    line.frustumCulled = false;

    const scene = new THREE.Scene();
    scene.add(line);

    return {
      anchor,
      render(renderer, mvp, aspectRatio) {
        const canvas = map.getCanvas();
        material.resolution.set(canvas.clientWidth, canvas.clientHeight);
        applyDecomposedMvp(camera, mvp, aspectRatio);
        renderer.resetState();
        renderer.render(scene, camera);
      },
      dispose() {
        geometry.dispose();
      },
    };
  }

  const id = options.id ?? `route-line-${anonymousRouteLineCount++}`;
  const layer = createAnchoredCustomLayer<RoutePoint>(id, map, { buildChunk });
  // Self-registers, matching `tier0-line.ts`'s contract: a `RouteLayer` is ready to `setData()`
  // immediately after construction, regardless of tier. A caller that also calls `map.addLayer`
  // itself would hit maplibre's own "Layer already exists" error — do not add it a second time.
  map.addLayer(layer);
  const lodThresholdZoom = options.lodThresholdZoom ?? DEFAULT_LOD_THRESHOLD_ZOOM;

  let fineChunks: RoutePoint[][] = [];
  /** The last selection actually applied, so `updateLod` can skip a no-op rebuild. */
  let lastSelectionKey: string | undefined;

  function applyLod() {
    if (fineChunks.length === 0) {
      layer.setChunks([]);
      lastSelectionKey = undefined;
      return;
    }
    const zoom = map.getZoom();
    const bounds = map.getBounds();
    const selection = selectChunksForLod(fineChunks, zoom, lodThresholdZoom, {
      west: bounds.getWest(),
      south: bounds.getSouth(),
      east: bounds.getEast(),
      north: bounds.getNorth(),
    });
    const key = selectionKey(fineChunks, selection);
    if (key === lastSelectionKey) return;
    lastSelectionKey = key;
    layer.setChunks(selection.chunks.map((chunk) => ({ anchor: chunk[0], points: chunk })));
  }

  return {
    ...layer,
    setData(points: RoutePoint[]) {
      fineChunks = chunkLine(points);
      lastSelectionKey = undefined; // force setData to always apply, even if the key would coincide
      applyLod();
    },
    updateLod: applyLod,
    dispose() {
      layer.disposeChunks();
      material.dispose();
    },
  };
}
