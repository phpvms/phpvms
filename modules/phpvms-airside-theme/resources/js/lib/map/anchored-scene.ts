import { MercatorCoordinate } from "maplibre-gl";
import type { CustomLayerInterface, Map as MapLibreMapType } from "maplibre-gl";
import * as THREE from "three";
import type { ScenePosition } from "./types.ts";

/**
 * The generic anchored-chunk substrate (design.md D2) — everything about
 * placing and lifecycle-managing anchored chunks that does NOT depend on
 * which technique (D3: `Line2` vs. tapered tube) draws inside them. A route
 * line (`route-line.ts`) and, later, waypoint markers both build on this.
 *
 * `modelMatrixFor(position, isGlobe)` is a per-location matrix, not a world
 * coordinate system, so vertex positions are expressed relative to a chunk's
 * own anchor: invert the anchor's model matrix and decompose every other
 * point's own model matrix against it (`decomposeLocal` below). Flat-earth
 * metre offsets would drift, and the matrix rotates and mirrors its axes
 * (design.md D2), so guessing at an offset is not an option.
 *
 * **maplibre-gl v6 removed the map transform, and with it the internal model
 * matrix helper this used to call** (v5-to-v6 migration guide: "use Map's
 * public API instead"). There is no public replacement, so the two matrices
 * below are built here, ported from MapLibre's own v6 example
 * `test/examples/add-a-3d-model-to-globe-using-threejs.html`. That example is
 * also where the mercator branch's `-scale` x-mirror comes from — the axis
 * flip design.md D2 already documented, now explicit rather than hidden inside
 * a removed internal.
 *
 * The old signature took the map only to reach its transform; nothing here
 * needs the map any more, so it takes the projection space instead. That is
 * deliberate: the caller already knows which space it is in (from
 * `projectionTransition`), and passing it makes the mercator/globe choice a
 * checked argument rather than an invisible dependency on map state.
 */

/** MapLibre's internal earth radius, matching its own globe example. */
const EARTH_RADIUS_M = 6371008.8;

type RenderArgs = {
  defaultProjectionData: {
    mainMatrix: number[];
    /**
     * MapLibre's globe↔mercator blend, 0 (mercator) to 1 (globe). A custom
     * layer only ever gets the pure-globe matrix mid-blend
     * (design.md D2/D6's acars citation), so this drives two behaviours:
     * never let the map idle mid-blend (the last mid-blend frame would
     * freeze on screen), and re-bake geometry whenever the space flips,
     * because the globe and mercator model matrices bake DIFFERENT local
     * frames (mercator mirrors x, globe does not) and geometry baked in one
     * renders mirrored when placed by the other.
     */
    projectionTransition?: number;
  };
};

/**
 * Place the model at a lng/lat/altitude in MapLibre's mercator space, scaling
 * metres into mercator units. The negative x scale is MapLibre's own mirror.
 */
function mercatorModelMatrix(position: ScenePosition): THREE.Matrix4 {
  const mercator = MercatorCoordinate.fromLngLat([position.lon, position.lat], position.altitude);
  const scale = mercator.meterInMercatorCoordinateUnits();
  return new THREE.Matrix4()
    .makeTranslation(mercator.x, mercator.y, mercator.z)
    .multiply(new THREE.Matrix4().makeRotationZ(Math.PI))
    .multiply(new THREE.Matrix4().makeRotationX(Math.PI / 2))
    .multiply(new THREE.Matrix4().makeScale(-scale, scale, scale));
}

/**
 * Rotate the model onto the unit sphere at the given lng/lat, lift it by its
 * altitude, and scale metres into unit-sphere units. No x-mirror here — that
 * asymmetry with mercator is exactly why a mirroring flip forces a re-bake.
 */
function globeModelMatrix(position: ScenePosition): THREE.Matrix4 {
  const scale = 1 / EARTH_RADIUS_M;
  return new THREE.Matrix4()
    .makeRotationY((position.lon / 180) * Math.PI)
    .multiply(new THREE.Matrix4().makeRotationX((-position.lat / 180) * Math.PI))
    .multiply(new THREE.Matrix4().makeTranslation(0, 0, 1 + position.altitude / EARTH_RADIUS_M))
    .multiply(new THREE.Matrix4().makeRotationX(Math.PI / 2))
    .multiply(new THREE.Matrix4().makeScale(scale, scale, scale));
}

/** The per-location model matrix for whichever projection is being rendered. */
export function modelMatrixFor(position: ScenePosition, isGlobe: boolean): THREE.Matrix4 {
  return isGlobe ? globeModelMatrix(position) : mercatorModelMatrix(position);
}

/**
 * A function that places any point relative to one chunk's anchor, by
 * inverting the anchor's own model matrix and decomposing the point's model
 * matrix against it.
 */
export function localPositionFn(
  anchor: ScenePosition,
  isGlobe: boolean,
): (point: ScenePosition) => THREE.Vector3 {
  const inverse = modelMatrixFor(anchor, isGlobe).invert();
  return (point: ScenePosition) =>
    new THREE.Vector3().setFromMatrixPosition(modelMatrixFor(point, isGlobe)).applyMatrix4(inverse);
}

/** State read from `RenderArgs` once per frame, plus the derived blend/mirroring signals. */
export type ProjectionFrame = {
  matrix: THREE.Matrix4;
  transition: number;
  /** Neither 0 nor 1 — the map is mid-transition and must be kept repainting. */
  midBlend: boolean;
  /** Which local frame (`true` = globe, `false` = mercator) geometry was last baked for. */
  globeNow: boolean;
};

/**
 * Read this frame's projection matrix and blend state, and say whether the
 * caller must (a) keep the render loop going (`midBlend`) and (b) re-bake all
 * chunk geometry because the mirroring flipped since `previousGlobeNow`.
 */
export function readProjectionFrame(
  args: unknown,
  previousGlobeNow: boolean | undefined,
): ProjectionFrame & { mirroringFlipped: boolean } {
  const { defaultProjectionData } = args as RenderArgs;
  const matrix = new THREE.Matrix4().fromArray(defaultProjectionData.mainMatrix);
  const transition = defaultProjectionData.projectionTransition ?? 0;
  const midBlend = transition > 0 && transition < 1;
  const globeNow = transition > 0;
  return {
    matrix,
    transition,
    midBlend,
    globeNow,
    mirroringFlipped: previousGlobeNow !== undefined && previousGlobeNow !== globeNow,
  };
}

/** One anchored chunk's drawable content, opaque to `anchored-scene.ts` beyond its anchor and lifecycle. */
export type AnchoredChunkHandle = {
  anchor: ScenePosition;
  /**
   * Draw this chunk. `mvp` is `projection * anchor's own model matrix` for
   * the current frame — the content decides how to point its camera at it
   * (`route-line.ts` uses `line2-camera.ts`'s `P⁻¹·MVP` split; a
   * `MeshBasicMaterial`-based content, e.g. future markers, can set
   * `camera.projectionMatrix` to `mvp` directly).
   */
  render(renderer: THREE.WebGLRenderer, mvp: THREE.Matrix4, aspectRatio: number): void;
  dispose(): void;
};

export type AnchoredChunkSource<TPoint> = { anchor: ScenePosition; points: TPoint[] };

export type AnchoredLayerCallbacks<TPoint> = {
  /**
   * Build one chunk's drawable content from its source points, in the given
   * projection space. Called on `setChunks` and on every re-bake, so `isGlobe`
   * is passed per call rather than captured once.
   */
  buildChunk(anchor: ScenePosition, points: TPoint[], isGlobe: boolean): AnchoredChunkHandle;
};

export type AnchoredCustomLayer<TPoint> = CustomLayerInterface & {
  /** Wholesale rebuild from a full chunk list (design.md D4 — no incremental append). */
  setChunks(chunks: AnchoredChunkSource<TPoint>[]): void;
  /** Chunks actually built, for callers (harness, LOD) that need to inspect the current state. */
  chunkCount(): number;
  /**
   * Dispose every current chunk's geometry without touching the renderer.
   * `onRemove` calls this too (plus the renderer) when MapLibre itself
   * removes the layer; content types with a shared resource across chunks
   * (`route-line.ts`'s one `LineMaterial`) call this directly from their own
   * `dispose()` so cleanup works whether or not the layer was ever added to a
   * map.
   */
  disposeChunks(): void;
};

/**
 * `CustomLayerInterface` scaffold shared by every anchored-chunk content
 * type: owns the `THREE.WebGLRenderer`, reads the projection matrix once per
 * frame, forces a repaint mid-blend, re-bakes on a mirroring flip, and calls
 * each chunk's own `render()` with its per-frame MVP. `renderingMode: '3d'`,
 * `autoClear = false` per design.md D2.
 */
export function createAnchoredCustomLayer<TPoint>(
  id: string,
  map: MapLibreMapType,
  callbacks: AnchoredLayerCallbacks<TPoint>,
): AnchoredCustomLayer<TPoint> {
  const projectionMatrix = new THREE.Matrix4();
  const mvp = new THREE.Matrix4();

  let renderer: THREE.WebGLRenderer | undefined;
  let sources: AnchoredChunkSource<TPoint>[] = [];
  let handles: AnchoredChunkHandle[] = [];
  let globeNow: boolean | undefined;
  /** Deferred: the bake needs the frame's projection space, which is only known inside `render`. */
  let dirty = true;

  function disposeHandles() {
    for (const handle of handles) handle.dispose();
    handles = [];
  }

  function rebuildAllChunks(isGlobe: boolean) {
    disposeHandles();
    handles = sources.map((source) => callbacks.buildChunk(source.anchor, source.points, isGlobe));
  }

  return {
    id,
    type: "custom",
    renderingMode: "3d",

    chunkCount: () => handles.length,

    setChunks(chunks) {
      sources = chunks;
      dirty = true;
    },

    disposeChunks: disposeHandles,

    onAdd(_addedMap, gl) {
      renderer = new THREE.WebGLRenderer({ canvas: map.getCanvas(), context: gl });
      renderer.autoClear = false;
    },

    onRemove() {
      disposeHandles();
      renderer?.dispose();
      renderer = undefined;
    },

    render(_gl, args) {
      if (!renderer) return;

      const frame = readProjectionFrame(args, globeNow);
      projectionMatrix.copy(frame.matrix);

      if (dirty || frame.mirroringFlipped) {
        dirty = false;
        rebuildAllChunks(frame.globeNow);
      }
      globeNow = frame.globeNow;

      // design.md D16 / tasks.md 2b.4: this is the ONE thing the package adds that could hold a
      // continuous repaint loop. Gated on page visibility so a hidden tab does not keep asking
      // MapLibre for another frame — resumes on its own next real render once visible again,
      // since `document.hidden` is read fresh here rather than cached from an event.
      if (frame.midBlend && !document.hidden) map.triggerRepaint();
      if (handles.length === 0) return;

      const canvas = map.getCanvas();
      const aspectRatio = canvas.clientWidth / Math.max(1, canvas.clientHeight);

      for (const handle of handles) {
        mvp.copy(projectionMatrix).multiply(modelMatrixFor(handle.anchor, frame.globeNow));
        handle.render(renderer, mvp, aspectRatio);
      }
    },
  };
}
