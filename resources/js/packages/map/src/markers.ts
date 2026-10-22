import type { Map as MapLibreMapType } from "maplibre-gl";
import * as THREE from "three";
// troika ships declarations under `dist/types` but names neither `types` nor `exports` in its
// package.json, so TypeScript resolves the bundle and finds nothing (same gap acars documents).
// @ts-expect-error untyped package
import { Text } from "troika-three-text";
import {
  type AnchoredChunkHandle,
  type AnchoredCustomLayer,
  createAnchoredCustomLayer,
} from "./anchored-scene.ts";
import type { ScenePosition } from "./types.ts";

/**
 * Waypoint markers and labels (design.md, tasks.md 2.8). Each waypoint is its
 * OWN anchor — unlike the route line, a marker has no extent to place
 * relative to a neighbour, so anchoring it at itself makes its local offset
 * exactly `(0, 0, 0)` rather than introducing the tangent-plane error a
 * shared line-chunk anchor would (a deliberate simplification vs.
 * `../acars/.../altitude-scene.ts`'s `groupFor`, which reuses a nearby line
 * chunk's anchor to save building an extra one; not needed here since a
 * marker group is cheap regardless).
 *
 * Built on `anchored-scene.ts`, but with a MERGED-MVP camera rather than
 * `line2-camera.ts`'s `P⁻¹·MVP` split: that split exists only because
 * `LineMaterial` reads view-space z for its near-plane trim (design.md D3).
 * `MeshBasicMaterial` (dots) and troika's `Text` material read no such thing,
 * so `camera.projectionMatrix = mvp` directly is exact — the same simpler
 * wiring `../acars/.../altitude-scene.ts` uses for its own tubes and markers.
 *
 * Everything here is CHROME, not scenery: `depthTest`/`depthWrite: false`, so
 * a waypoint never loses a depth test to the route line it names (which sits
 * nearer the camera at the point the marker names). `renderOrder` layers
 * casing under fill under label within that no-depth pass.
 */

const CASING_COLOR = "#0b1420";
const LABEL_COLOR = "#f4f4f5";
const LABEL_SIZE_PX = 22;
const LABEL_OUTLINE = "8%";
const LABEL_LETTER_SPACING = 0.06;
const LABEL_OFFSET_PX = 15;
const DOT_CASING_PX = 2;
const SAMPLE_DISTANCE_M = 1_000;
const ORIGIN = new THREE.Vector3(0, 0, 0);

const RENDER_ORDER = { casing: 10, fill: 11, label: 12 } as const;

/** A clip-space corner pushed back through an inverted matrix. NaN when `w` comes out 0. */
function unprojectClip(inverse: THREE.Matrix4, x: number, y: number, z: number): THREE.Vector3 {
  const point = new THREE.Vector4(x, y, z, 1).applyMatrix4(inverse);
  return new THREE.Vector3(point.x / point.w, point.y / point.w, point.z / point.w);
}

function usable(v: THREE.Vector3): boolean {
  return Number.isFinite(v.x) && Number.isFinite(v.y) && Number.isFinite(v.z) && v.lengthSq() > 0;
}

/**
 * Which way the viewer is looking, and which way is up on screen, both in a
 * chunk's local frame. Read out of the chunk's own MVP rather than the
 * camera — `getMatrixForModel` rotates and mirrors its axes, so no local axis
 * reliably means "up", and `getCameraLngLat`/`getCameraAltitude` are
 * unreliable under the globe transform (design.md, ported from
 * `../acars/.../altitude-scene.ts` `viewBasisIn`).
 */
export function viewBasisIn(
  mvp: THREE.Matrix4,
): { forward: THREE.Vector3; up: THREE.Vector3 } | undefined {
  const inverse = new THREE.Matrix4().copy(mvp).invert();
  const centre = unprojectClip(inverse, 0, 0, -1);
  const forward = unprojectClip(inverse, 0, 0, 1).sub(centre);
  const up = unprojectClip(inverse, 0, 1, -1).sub(centre);
  if (!usable(forward) || !usable(up)) return undefined;
  return { forward: forward.normalize(), up: up.normalize() };
}

function toPixels(
  mvp: THREE.Matrix4,
  point: THREE.Vector3,
  width: number,
  height: number,
): { x: number; y: number } | undefined {
  const clip = new THREE.Vector4(point.x, point.y, point.z, 1).applyMatrix4(mvp);
  if (!(clip.w > 0) || !Number.isFinite(clip.w)) return undefined;
  return {
    x: ((clip.x / clip.w) * 0.5 + 0.5) * width,
    y: (0.5 - (clip.y / clip.w) * 0.5) * height,
  };
}

/**
 * Metres per CSS pixel at this chunk, measured through its own matrix along a
 * screen-parallel direction (`across`, from `viewBasisIn`). `undefined` when
 * unmeasurable (chunk behind the viewer, degenerate sample) — the caller
 * hides the marker that frame rather than guessing a scale, which is what
 * once drew a screen-filling slab (ported from `../acars/.../altitude-scene.ts`
 * `metresPerPixelIn`, minus its `inheritScales` neighbour-fallback: a marker
 * anchored at itself has no neighbour to inherit from that is any more
 * measured than it is).
 */
export function metresPerPixelIn(
  mvp: THREE.Matrix4,
  across: THREE.Vector3,
  width: number,
  height: number,
  distanceM = SAMPLE_DISTANCE_M,
): number | undefined {
  const origin = toPixels(mvp, ORIGIN, width, height);
  const offset = toPixels(mvp, across.clone().normalize().multiplyScalar(distanceM), width, height);
  if (!origin || !offset) return undefined;
  const apart = Math.hypot(offset.x - origin.x, offset.y - origin.y);
  if (!Number.isFinite(apart) || apart < 1e-6) return undefined;
  return distanceM / apart;
}

/** A dot marker: a casing rim (so it reads against a same-coloured route) plus a filled circle. */
export function buildDotMarker(radiusPx: number, color: string): THREE.Group {
  const group = new THREE.Group();
  const casing = new THREE.Mesh(
    new THREE.CircleGeometry(radiusPx + DOT_CASING_PX, 24),
    new THREE.MeshBasicMaterial({ color: CASING_COLOR, depthTest: false, depthWrite: false }),
  );
  const fill = new THREE.Mesh(
    new THREE.CircleGeometry(radiusPx, 24),
    new THREE.MeshBasicMaterial({ color, depthTest: false, depthWrite: false }),
  );
  casing.renderOrder = RENDER_ORDER.casing;
  fill.renderOrder = RENDER_ORDER.fill;
  casing.frustumCulled = false;
  fill.frustumCulled = false;
  group.add(casing, fill);
  return group;
}

export type LabelFont = {
  /**
   * A `.woff` (NOT `.woff2` — troika throws "woff2 fonts not supported")
   * font file URL. Troika's default (`font: null`) fetches Roboto from
   * Google Fonts, a CDN dependency inappropriate for an offline-capable app;
   * there is deliberately no default here, so a caller must decide where its
   * label font comes from rather than silently picking one up.
   */
  url: string;
};

/**
 * Troika's `Text.material` getter returns EITHER a single derived `Material` OR, once an outline
 * is configured (`hasOutline()` — `outlineWidth`/`outlineBlur`/`outlineOffsetX/Y`, and
 * `buildLabelMarker` below always sets `outlineWidth`), an ARRAY of two:
 * `[outlineMaterial, derivedMaterial]` (troika-three-text's own `get material()`, confirmed by
 * reading its source — `dist/troika-three-text.esm.js`). Every label built here therefore always
 * has an array. Confirmed a real bug (codex review, verified), two-fold:
 * - `text.material.depthTest = false` used to write a THROWAWAY property onto the ARRAY object
 *   itself (arrays are objects; this doesn't throw, it just does nothing) — the outline's actual
 *   depth behaviour was never configured, contrary to what this module's own file-level doc
 *   comment claims ("depthTest`/`depthWrite: false`, so a waypoint never loses a depth test...").
 * - `text.material?.dispose()` at teardown called `.dispose` on the array, which doesn't exist —
 *   `TypeError: text.material.dispose is not a function`. Thrown from inside one marker's
 *   `dispose()`, this aborts `anchored-scene.ts`'s `disposeHandles()` `for` loop (no try/catch),
 *   so EVERY marker after the first in a multi-marker layer leaked its geometry/materials too.
 */
export function eachTextMaterial(
  text: InstanceType<typeof Text>,
  fn: (material: THREE.Material) => void,
): void {
  const material = text.material as THREE.Material | THREE.Material[];
  if (Array.isArray(material)) {
    for (const m of material) fn(m);
  } else {
    fn(material);
  }
}

/** A waypoint text label: above the point (never beside it, so the route line never runs through it). */
export function buildLabelMarker(content: string, font: LabelFont): THREE.Group {
  const group = new THREE.Group();
  const text = new Text();
  text.text = content;
  text.font = font.url;
  text.fontSize = LABEL_SIZE_PX;
  text.color = LABEL_COLOR;
  text.outlineWidth = LABEL_OUTLINE;
  text.outlineColor = CASING_COLOR;
  text.letterSpacing = LABEL_LETTER_SPACING;
  text.anchorX = "center";
  text.anchorY = "bottom";
  text.position.y = LABEL_OFFSET_PX;
  text.frustumCulled = false;
  text.renderOrder = RENDER_ORDER.label;
  eachTextMaterial(text, (material) => {
    material.depthTest = false;
    material.depthWrite = false;
  });
  text.sync();
  group.add(text);
  return group;
}

export type Waypoint = ScenePosition & { label?: string; dotColor?: string; dotRadiusPx?: number };

export type WaypointMarkersOptions = {
  font: LabelFont;
  defaultDotColor?: string;
  defaultDotRadiusPx?: number;
  /** MapLibre layer id — see `RouteLineOptions.id` in `route-line.ts` for why this must be unique per instance. */
  id?: string;
};

export type WaypointMarkers = AnchoredCustomLayer<Waypoint> & {
  setData(waypoints: Waypoint[]): void;
};

const DEFAULT_DOT_RADIUS_PX = 4;
const DEFAULT_DOT_COLOR = "#e879f9";
let anonymousWaypointMarkersCount = 0;

export function createWaypointMarkers(
  map: MapLibreMapType,
  options: WaypointMarkersOptions,
): WaypointMarkers {
  const camera = new THREE.Camera();
  camera.matrixAutoUpdate = false;
  camera.matrixWorldAutoUpdate = false;
  const facingMatrix = new THREE.Matrix4();

  function buildChunk(anchor: ScenePosition, points: Waypoint[]): AnchoredChunkHandle {
    const waypoint = points[0];
    const scene = new THREE.Scene();
    const dot = buildDotMarker(
      waypoint.dotRadiusPx ?? options.defaultDotRadiusPx ?? DEFAULT_DOT_RADIUS_PX,
      waypoint.dotColor ?? options.defaultDotColor ?? DEFAULT_DOT_COLOR,
    );
    scene.add(dot);
    if (waypoint.label) scene.add(buildLabelMarker(waypoint.label, options.font));

    return {
      anchor,
      render(renderer, mvp, aspectRatio) {
        void aspectRatio;
        const canvas = map.getCanvas();
        const basis = viewBasisIn(mvp);
        const scale = basis
          ? metresPerPixelIn(mvp, basis.up, canvas.clientWidth, canvas.clientHeight)
          : undefined;

        if (!basis || scale === undefined) {
          // Cannot measure this frame (behind the viewer, degenerate sample) — hide rather than
          // guess a scale, which is what once drew a screen-filling slab.
          scene.visible = false;
        } else {
          scene.visible = true;
          facingMatrix.lookAt(basis.forward.clone().negate(), ORIGIN, basis.up);
          scene.quaternion.setFromRotationMatrix(facingMatrix);
          scene.scale.setScalar(scale);
        }

        camera.projectionMatrix.copy(mvp);
        renderer.resetState();
        renderer.render(scene, camera);
      },
      dispose() {
        for (const child of dot.children) {
          if (child instanceof THREE.Mesh) {
            child.geometry.dispose();
            (child.material as THREE.Material).dispose();
          }
        }
        for (const child of scene.children) {
          // Troika's `Text.dispose()` only releases its own geometry — "we don't also dispose
          // the derived material here... users can dispose the base material manually" (troika
          // source comment) — so the material is disposed alongside it, not left for GC.
          // `eachTextMaterial` handles both shapes `text.material` can be (see its own doc
          // comment) — this used to assume a single `Material` and threw on every labelled
          // marker, since `outlineWidth` is always set here.
          if (child instanceof Text) {
            const text = child as InstanceType<typeof Text> & { dispose(): void };
            text.dispose();
            eachTextMaterial(text, (material) => material.dispose());
          }
        }
      },
    };
  }

  const id = options.id ?? `waypoint-markers-${anonymousWaypointMarkersCount++}`;
  const layer = createAnchoredCustomLayer<Waypoint>(id, map, { buildChunk });
  // Self-registers — see `route-line.ts`'s matching comment. A caller must not also `map.addLayer` this.
  map.addLayer(layer);

  return {
    ...layer,
    setData(waypoints: Waypoint[]) {
      layer.setChunks(waypoints.map((waypoint) => ({ anchor: waypoint, points: [waypoint] })));
    },
  };
}
