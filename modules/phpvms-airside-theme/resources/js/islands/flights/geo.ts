/**
 * Great-circle geometry (path, bearing, distance) shared by the route map and
 * the briefing globe. Coordinates are [lon, lat].
 */
export type LonLat = [lon: number, lat: number];

const R = Math.PI / 180;
const D = 180 / Math.PI;
const EARTH_NM = 3440.065;

/** Densified great circle (slerp) so the line curves on the globe. Returns n + 1 points. */
export function greatCircle(a: LonLat, b: LonLat, n = 160): LonLat[] {
  const la1 = a[1] * R;
  const lo1 = a[0] * R;
  const la2 = b[1] * R;
  const lo2 = b[0] * R;
  const d =
    2 *
    Math.asin(
      Math.sqrt(
        Math.sin((la2 - la1) / 2) ** 2 +
          Math.cos(la1) * Math.cos(la2) * Math.sin((lo2 - lo1) / 2) ** 2,
      ),
    );

  if (d === 0) return [a, b];

  const out: LonLat[] = [];
  for (let i = 0; i <= n; i++) {
    const f = i / n;
    const A = Math.sin((1 - f) * d) / Math.sin(d);
    const B = Math.sin(f * d) / Math.sin(d);
    const x = A * Math.cos(la1) * Math.cos(lo1) + B * Math.cos(la2) * Math.cos(lo2);
    const y = A * Math.cos(la1) * Math.sin(lo1) + B * Math.cos(la2) * Math.sin(lo2);
    const z = A * Math.sin(la1) + B * Math.sin(la2);
    out.push([Math.atan2(y, x) * D, Math.atan2(z, Math.hypot(x, y)) * D]);
  }

  return out;
}

/** Initial great-circle bearing a to b, in degrees (0..360). */
export function bearing(a: LonLat, b: LonLat): number {
  const la1 = a[1] * R;
  const la2 = b[1] * R;
  const dl = (b[0] - a[0]) * R;
  const deg =
    Math.atan2(
      Math.sin(dl) * Math.cos(la2),
      Math.cos(la1) * Math.sin(la2) - Math.sin(la1) * Math.cos(la2) * Math.cos(dl),
    ) * D;

  return (deg + 360) % 360;
}

/** Great-circle distance a to b in nautical miles. */
export function distanceNm(a: LonLat, b: LonLat): number {
  const la1 = a[1] * R;
  const la2 = b[1] * R;
  const dla = (b[1] - a[1]) * R;
  const dlo = (b[0] - a[0]) * R;
  const h = Math.sin(dla / 2) ** 2 + Math.cos(la1) * Math.cos(la2) * Math.sin(dlo / 2) ** 2;

  return 2 * EARTH_NM * Math.asin(Math.sqrt(h));
}

/** The nav display's readout: track, distance, a rough ETE at 460 kt, and the level. */
export function readout(from: LonLat, to: LonLat | null, cruiseLevel: number | null | undefined): string[] {
  const level = cruiseLevel == null ? "FL380" : `FL${cruiseLevel}`;

  if (!to) {
    return ["TRK ---°", "DIST ----NM", "ETE --:--", level];
  }

  const hours = distanceNm(from, to) / 460;
  const h = Math.floor(hours);
  const m = Math.round((hours - h) * 60);
  const pad = (value: number): string => String(value).padStart(2, "0");

  return [
    `TRK ${pad(Math.round(bearing(from, to))).padStart(3, "0")}°`,
    `DIST ${Math.round(distanceNm(from, to))}NM`,
    `ETE ${pad(h)}:${pad(m)}`,
    level,
  ];
}
