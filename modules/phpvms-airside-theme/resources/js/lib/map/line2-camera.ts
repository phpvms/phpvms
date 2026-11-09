import * as THREE from "three";

/**
 * Drive three's screen-space fat lines (Line2 / LineMaterial) from a MapLibre
 * custom-layer matrix (design.md D3; verdict recorded there — tasks.md 2.0).
 *
 * Ported near-verbatim from
 * `../acars/src/Acars.WebUI/webui/src/lib/components/maplibre/layers/line2-camera.ts`,
 * which already solved the wiring problem below. Consumed by `route-line.ts`.
 *
 * LineMaterial cannot take the merged MVP as `camera.projectionMatrix` the way
 * a `MeshBasicMaterial` layer could: its vertex shader transforms segment ends
 * by `modelViewMatrix` and uses their VIEW-SPACE z to trim segments at the near
 * plane (`LineMaterial.js:101,131,142`). With the MVP merged into the
 * projection, "view z" is raw local z, the trim fires on garbage, and the line
 * deforms as the camera moves — the documented failure of the naive wiring.
 *
 * The split does NOT need MapLibre's own projection matrix. For any invertible
 * standard perspective P (bottom row `[0,0,-1,0]`), setting projection = P and
 * view = P⁻¹·MVP leaves every clip position identical (P · P⁻¹ · MVP = MVP),
 * and the RAW homogeneous view z the shader computes
 * (`modelViewMatrix * vec4(position, 1.0)`, never divided) is exactly `-clip.w`
 * — the honest behind-the-camera depth — because a standard P⁻¹ reads view z
 * from clip w alone. P⁻¹·MVP is generally NOT affine (its transformed w is not
 * 1), which would matter to code that perspective-divides view coordinates;
 * LineMaterial never does. The only thing P's own values influence is the
 * trim's near estimate, which reads P's near plane (`trimSegmentAlpha`,
 * `LineMaterial.js:73-86`), in the MVP's own w units.
 */

const NEAR_M = 1;
const FAR_M = 10_000_000;
const FOV_DEG = 45;

/** Any standard perspective works; this one exists so both halves agree on it. */
export function decomposedProjection(aspect: number): THREE.Matrix4 {
  const top = NEAR_M * Math.tan((FOV_DEG * Math.PI) / 360);
  const right = top * (aspect > 0 ? aspect : 1);
  return new THREE.Matrix4().makePerspective(-right, right, top, -top, NEAR_M, FAR_M);
}

/** The view half: `P⁻¹ · mvp`. Exact by construction, not an approximation. */
export function decomposedView(projection: THREE.Matrix4, mvp: THREE.Matrix4): THREE.Matrix4 {
  return projection.clone().invert().multiply(mvp);
}

/**
 * Point `camera` at one custom-layer MVP so materials that read projection and
 * view separately see honest matrices. `matrixWorldAutoUpdate` goes off because
 * the renderer would otherwise rebuild `matrixWorld` from the camera's
 * (identity) position and clobber this (`three.module.js:17633`).
 */
export function applyDecomposedMvp(camera: THREE.Camera, mvp: THREE.Matrix4, aspect: number): void {
  camera.matrixAutoUpdate = false;
  camera.matrixWorldAutoUpdate = false;
  const projection = decomposedProjection(aspect);
  camera.projectionMatrix.copy(projection);
  camera.projectionMatrixInverse.copy(projection).invert();
  camera.matrixWorldInverse.copy(decomposedView(projection, mvp));
  camera.matrixWorld.copy(camera.matrixWorldInverse).invert();
}
