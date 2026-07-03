/**
 * Pure great-circle geometry for the Nav Display. No framework, no map lib.
 * Coordinates are [lon, lat] (GeoJSON order) throughout.
 */

export type LngLat = [number, number]

const R = Math.PI / 180
const D = 180 / Math.PI
const EARTH_NM = 3440.065 // earth radius in nautical miles

/**
 * Densified great-circle (slerp) between two points so it curves smoothly on a
 * globe projection. Returns `n + 1` [lon, lat] points.
 */
export function greatCircle(a: LngLat, b: LngLat, n = 160): LngLat[] {
  const la1 = a[1] * R, lo1 = a[0] * R, la2 = b[1] * R, lo2 = b[0] * R
  const d =
    2 *
    Math.asin(
      Math.sqrt(
        Math.sin((la2 - la1) / 2) ** 2 +
          Math.cos(la1) * Math.cos(la2) * Math.sin((lo2 - lo1) / 2) ** 2,
      ),
    )
  if (d === 0) return [a, b]
  const out: LngLat[] = []
  for (let i = 0; i <= n; i++) {
    const f = i / n
    const A = Math.sin((1 - f) * d) / Math.sin(d)
    const B = Math.sin(f * d) / Math.sin(d)
    const x = A * Math.cos(la1) * Math.cos(lo1) + B * Math.cos(la2) * Math.cos(lo2)
    const y = A * Math.cos(la1) * Math.sin(lo1) + B * Math.cos(la2) * Math.sin(lo2)
    const z = A * Math.sin(la1) + B * Math.sin(la2)
    out.push([Math.atan2(y, x) * D, Math.atan2(z, Math.hypot(x, y)) * D])
  }
  return out
}

/** Initial great-circle bearing a→b, in degrees (0..360). */
export function bearing(a: LngLat, b: LngLat): number {
  const la1 = a[1] * R, la2 = b[1] * R, dl = (b[0] - a[0]) * R
  const deg =
    Math.atan2(
      Math.sin(dl) * Math.cos(la2),
      Math.cos(la1) * Math.sin(la2) - Math.sin(la1) * Math.cos(la2) * Math.cos(dl),
    ) * D
  return (deg + 360) % 360
}

/** Great-circle distance a→b in nautical miles. */
export function distanceNm(a: LngLat, b: LngLat): number {
  const la1 = a[1] * R, la2 = b[1] * R
  const dla = (b[1] - a[1]) * R, dlo = (b[0] - a[0]) * R
  const h = Math.sin(dla / 2) ** 2 + Math.cos(la1) * Math.cos(la2) * Math.sin(dlo / 2) ** 2
  return 2 * EARTH_NM * Math.asin(Math.sqrt(h))
}

/** Midpoint of the [center] the globe should frame for a route. */
export function routeCenter(a: LngLat, b: LngLat): LngLat {
  const mid = greatCircle(a, b, 2)[1]
  return mid ?? [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2]
}
