import { describe, expect, it } from "vitest";
import * as THREE from "three";
import { applyDecomposedMvp, decomposedProjection, decomposedView } from "./line2-camera.ts";

/**
 * Ported from
 * `../acars/src/Acars.WebUI/webui/src/lib/components/maplibre/layers/line2-camera.test.ts`.
 *
 * The claim the whole spike rests on, proven rather than assumed: splitting a
 * merged MVP as P × (P⁻¹·MVP) changes no clip position, and hands LineMaterial
 * a view-space z that equals -clip.w — the true behind-the-camera depth its
 * near-plane trim needs.
 */

const ASPECT = 800 / 600;

/** A merged MVP like the one MapLibre hands a custom layer: some other camera's P′·V′. */
function mergedMvp(): THREE.Matrix4 {
  const other = new THREE.PerspectiveCamera(50, ASPECT, 2, 500_000);
  other.position.set(1200, 3400, 5600);
  other.lookAt(-300, 150, 0);
  other.updateMatrixWorld();
  return new THREE.Matrix4().multiplyMatrices(other.projectionMatrix, other.matrixWorldInverse);
}

const POINTS = [
  new THREE.Vector3(0, 0, 0),
  new THREE.Vector3(100, -2000, 350),
  new THREE.Vector3(-15_000, 8_000, 4),
  new THREE.Vector3(9000, 9000, -9000),
];

describe("decomposed MVP", () => {
  it("reconstructs the merged matrix exactly: P × (P⁻¹·MVP) = MVP", () => {
    const mvp = mergedMvp();
    const projection = decomposedProjection(ASPECT);
    const view = decomposedView(projection, mvp);

    const rebuilt = projection.clone().multiply(view);
    // Relative, because element magnitudes span orders of magnitude.
    for (let i = 0; i < 16; i++) {
      const want = mvp.elements[i];
      expect(rebuilt.elements[i]).toBeCloseTo(want, Math.abs(want) > 1 ? 6 : 9);
    }
  });

  it("gives raw (homogeneous) view-space z equal to -clip.w, in front and behind", () => {
    // This is what LineMaterial's segment trim consumes: `modelViewMatrix * vec4(position, 1.0)`
    // WITHOUT a perspective divide, then `start.z` (`LineMaterial.js:101,131`). The un-divided z
    // row of P⁻¹·MVP is exactly -row4(MVP), so raw z is the true behind-the-camera depth. NOTE
    // Vector3.applyMatrix4 would divide by w — P⁻¹·MVP is not affine, so the divided z differs;
    // the shader never divides, and neither may this assertion.
    const mvp = mergedMvp();
    const projection = decomposedProjection(ASPECT);
    const view = decomposedView(projection, mvp);

    for (const point of POINTS) {
      const clip = new THREE.Vector4(point.x, point.y, point.z, 1).applyMatrix4(mvp);
      const inView = new THREE.Vector4(point.x, point.y, point.z, 1).applyMatrix4(view);
      expect(inView.z).toBeCloseTo(-clip.w, 6);
    }
  });

  it("sets the camera up so the renderer cannot clobber it", () => {
    const camera = new THREE.Camera();
    applyDecomposedMvp(camera, mergedMvp(), ASPECT);

    expect(camera.matrixWorldAutoUpdate).toBe(false);
    expect(camera.matrixAutoUpdate).toBe(false);
    // matrixWorld is the inverse of the view we set, so modelViewMatrix = view · identity.
    const identity = camera.matrixWorld.clone().multiply(camera.matrixWorldInverse);
    for (let i = 0; i < 16; i++) {
      expect(identity.elements[i]).toBeCloseTo(i % 5 === 0 ? 1 : 0, 6);
    }
  });
});
