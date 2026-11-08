import * as THREE from "three";

/**
 * Hex → float RGB via `THREE.Color`, which THREE's `ColorManagement`
 * linearizes on construction (on by default since three r152).
 *
 * This is the RIGHT conversion for vertex-colour data, confirmed empirically
 * during the tasks.md 2.0 spike: `LineMaterial`'s shader computes in linear
 * space and its output chunk re-encodes to sRGB on the way out, so the two
 * conversions cancel and the DISPLAYED pixel matches the original hex exactly
 * (a `gl.readPixels()` scan found the raw byte triple for a known hex on
 * screen). Do NOT use this to build a target for comparing AGAINST rendered
 * pixel bytes — that needs the raw, non-linearized integers instead, because
 * matching linearized floats against sRGB display bytes silently matches
 * nothing (the bug the spike hit; see the report in design.md).
 */
export function hexToLinearRgb(hex: string): [number, number, number] {
  const color = new THREE.Color(hex);
  return [color.r, color.g, color.b];
}
