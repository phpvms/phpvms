import { describe, expect, it, vi } from "vitest";
import * as THREE from "three";
import { buildDotMarker, eachTextMaterial, metresPerPixelIn, viewBasisIn } from "./markers.ts";

/**
 * `buildLabelMarker` is deliberately NOT unit-tested here: it calls troika's
 * `Text.sync()`, which fetches and parses a font file — real network/SDF
 * work this suite has no business doing. Its label-specific properties
 * (position, colour, outline, renderOrder, depthTest) are plain assignments,
 * inspectable by reading the function; the harness is where it gets a live
 * check (tasks.md 2.9).
 */

describe("viewBasisIn", () => {
  it("gives an orthonormal-ish forward/up for an ordinary camera-like MVP", () => {
    const camera = new THREE.PerspectiveCamera(50, 4 / 3, 1, 1000);
    camera.position.set(0, 0, 100);
    camera.lookAt(0, 0, 0);
    camera.updateMatrixWorld();
    const mvp = new THREE.Matrix4().multiplyMatrices(
      camera.projectionMatrix,
      camera.matrixWorldInverse,
    );

    const basis = viewBasisIn(mvp);
    expect(basis).toBeDefined();
    expect(basis!.forward.length()).toBeCloseTo(1);
    expect(basis!.up.length()).toBeCloseTo(1);
    // The camera looks down -z from (0,0,100), so "forward" (into the screen) should point -z-ish.
    expect(basis!.forward.z).toBeLessThan(0);
  });

  it("is undefined for a singular (non-invertible) matrix", () => {
    const singular = new THREE.Matrix4().set(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
    expect(viewBasisIn(singular)).toBeUndefined();
  });
});

describe("metresPerPixelIn", () => {
  it("returns a smaller metres-per-pixel (finer resolution) for a nearer camera", () => {
    function mvpAtDistance(distance: number): THREE.Matrix4 {
      const camera = new THREE.PerspectiveCamera(50, 4 / 3, 1, 100_000);
      camera.position.set(0, 0, distance);
      camera.lookAt(0, 0, 0);
      camera.updateMatrixWorld();
      return new THREE.Matrix4().multiplyMatrices(
        camera.projectionMatrix,
        camera.matrixWorldInverse,
      );
    }

    const near = mvpAtDistance(100);
    const far = mvpAtDistance(10_000);
    const basisNear = viewBasisIn(near)!;
    const basisFar = viewBasisIn(far)!;

    const nearScale = metresPerPixelIn(near, basisNear.up, 800, 600);
    const farScale = metresPerPixelIn(far, basisFar.up, 800, 600);
    expect(nearScale).toBeDefined();
    expect(farScale).toBeDefined();
    expect(nearScale!).toBeLessThan(farScale!);
  });

  it("is undefined when the sample point does not project (behind the camera)", () => {
    // A degenerate all-zero matrix projects everything to w=0, never "in front".
    const degenerate = new THREE.Matrix4().set(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1);
    expect(metresPerPixelIn(degenerate, new THREE.Vector3(1, 0, 0), 800, 600)).toBeUndefined();
  });
});

describe("eachTextMaterial", () => {
  it("calls fn once for a single (non-outlined) material", () => {
    const material = new THREE.MeshBasicMaterial();
    const fakeText = { material } as never;
    const seen: THREE.Material[] = [];
    eachTextMaterial(fakeText, (m) => seen.push(m));
    expect(seen).toEqual([material]);
  });

  it("calls fn for EACH material when troika returns an array (outlineWidth set) — the disposal regression", () => {
    // Regression for a real bug (codex review, verified against troika-three-text's own source,
    // `dist/troika-three-text.esm.js`'s `get material()`): once an outline is configured — which
    // `buildLabelMarker` always does — `text.material` is `[outlineMaterial, derivedMaterial]`,
    // not a single `Material`. The old dispose code called `.dispose()` directly on this value,
    // which doesn't exist on an array and threw `TypeError`, aborting cleanup for every marker
    // after the first in the same layer (`anchored-scene.ts`'s `disposeHandles` has no try/catch).
    const outline = new THREE.MeshBasicMaterial();
    const derived = new THREE.MeshBasicMaterial();
    const fakeText = { material: [outline, derived] } as never;
    const seen: THREE.Material[] = [];
    eachTextMaterial(fakeText, (m) => seen.push(m));
    expect(seen).toEqual([outline, derived]);
  });

  it("does not throw calling .dispose() through the helper on an array-shaped material", () => {
    const dispose1 = vi.fn();
    const dispose2 = vi.fn();
    const fakeText = { material: [{ dispose: dispose1 }, { dispose: dispose2 }] } as never;
    expect(() => eachTextMaterial(fakeText, (m) => m.dispose())).not.toThrow();
    expect(dispose1).toHaveBeenCalledTimes(1);
    expect(dispose2).toHaveBeenCalledTimes(1);
  });
});

describe("buildDotMarker", () => {
  it("builds a casing ring behind a filled circle, both depth-test-free chrome", () => {
    const marker = buildDotMarker(5, "#22c55e");
    expect(marker.children).toHaveLength(2);
    const [casing, fill] = marker.children as THREE.Mesh[];

    expect(casing.renderOrder).toBeLessThan(fill.renderOrder);
    for (const mesh of [casing, fill]) {
      const material = mesh.material as THREE.MeshBasicMaterial;
      expect(material.depthTest).toBe(false);
      expect(material.depthWrite).toBe(false);
      expect(mesh.frustumCulled).toBe(false);
    }

    // Casing is the LARGER circle — it is a rim around the fill, not inside it.
    const casingGeometry = casing.geometry as THREE.CircleGeometry;
    const fillGeometry = fill.geometry as THREE.CircleGeometry;
    expect(casingGeometry.parameters.radius).toBeGreaterThan(fillGeometry.parameters.radius);
  });
});
