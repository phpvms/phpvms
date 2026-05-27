/**
 * Geographic helpers — distance in nautical miles between two airports.
 *
 * Mirrors `RouteForgeController::haversineNm()` exactly (same Earth radius,
 * same float arithmetic) so the client-side row distance matches the
 * server's `/preview-airports?near=…` decoration to within float rounding.
 * The L2 lint rule (range mismatch) reads `row.distance_nm` so any drift
 * here would surface as TS↔PHP lint disagreement.
 */

const EARTH_RADIUS_NM = 3440.065;

/**
 * Great-circle distance in nautical miles between (latA, lonA) and (latB, lonB).
 *
 * Latitude/longitude in decimal degrees. Returns 0 if either lat is exactly 0
 * AND lon is exactly 0 only when both endpoints coincide; otherwise the
 * standard haversine formula. No special-casing — caller is responsible for
 * filtering airports without coordinates before invoking.
 */
export function haversineNm(latA: number, lonA: number, latB: number, lonB: number): number {
  const phi1 = toRad(latA);
  const phi2 = toRad(latB);
  const dPhi = toRad(latB - latA);
  const dLambda = toRad(lonB - lonA);

  const a = Math.sin(dPhi / 2) ** 2 + Math.cos(phi1) * Math.cos(phi2) * Math.sin(dLambda / 2) ** 2;
  const c = 2 * Math.asin(Math.min(1, Math.sqrt(a)));

  return EARTH_RADIUS_NM * c;
}

function toRad(deg: number): number {
  return (deg * Math.PI) / 180;
}
