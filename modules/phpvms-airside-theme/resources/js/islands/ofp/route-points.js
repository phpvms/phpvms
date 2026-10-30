/**
 * Pure route geometry for the briefing globe, ported from skylight's
 * BriefingRouteGlobe and its geo helpers. Coordinates are [lon, lat].
 */
const R = Math.PI / 180;
const D = 180 / Math.PI;

/** Two points are the same place if they agree to about 1 km. */
const SAME_PLACE_DEGREES = 0.01;

/** Densified great circle (slerp) so the line curves on the globe. Returns n + 1 points. */
export function greatCircle(a, b, n = 160) {
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

  const out = [];
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

function isSamePlace(point, airport) {
  return (
    Math.abs(point.lon - airport[0]) < SAME_PLACE_DEGREES &&
    Math.abs(point.lat - airport[1]) < SAME_PLACE_DEGREES
  );
}

/**
 * Group the navlog into legs and label each one: consecutive fixes sharing a
 * `viaAirway` form a leg, every leg is `enroute`, then the first leg becomes
 * `sid` and the last `star` when any of its fixes carries the SID/STAR flag.
 */
export function classifyFixes(fixes) {
  if (fixes.length === 0) return [];

  const runs = [];
  for (const fix of fixes) {
    const current = runs.at(-1);
    if (current && current[0].viaAirway === fix.viaAirway) current.push(fix);
    else runs.push([fix]);
  }

  const kinds = runs.map(() => "enroute");
  if (runs[0].some((fix) => fix.isSidStar)) kinds[0] = "sid";
  if (runs.length > 1 && runs.at(-1).some((fix) => fix.isSidStar)) kinds[kinds.length - 1] = "star";

  return runs.flatMap((run, index) =>
    run.map((fix) => ({ lat: fix.lat, lon: fix.lon, ident: fix.ident, kind: kinds[index] })),
  );
}

function pointOf(airport) {
  return airport?.lat != null && airport.lon != null ? [airport.lon, airport.lat] : null;
}

/**
 * The navlog's classified fixes bounded by the airports. SimBrief's navlog ends
 * on the destination but starts on the first climb waypoint, so each end is
 * added only when the navlog does not already reach it.
 */
export function routeFixes(plannedFixes, departure, arrival) {
  const fixes = classifyFixes(plannedFixes);
  if (fixes.length === 0) return fixes;

  const from = pointOf(departure);
  if (from && !isSamePlace(fixes[0], from)) {
    fixes.unshift({
      lat: from[1],
      lon: from[0],
      ident: departure.icao ?? null,
      kind: fixes[0].kind,
    });
  }

  const to = pointOf(arrival);
  const last = fixes.at(-1);
  if (to && last && !isSamePlace(last, to)) {
    fixes.push({ lat: to[1], lon: to[0], ident: arrival.icao ?? null, kind: last.kind });
  }

  return fixes;
}

/**
 * Everything the globe draws: `points` for the line (the navlog, else a great
 * circle between the airports), `waypoints` for the dots and labels (navlog
 * only) and `airports` for the two endpoints.
 */
export function routeGeometry({ plannedFixes, departure, arrival }) {
  const fixes = routeFixes(plannedFixes ?? [], departure, arrival);
  const from = pointOf(departure);
  const to = pointOf(arrival);

  const fixPoints = fixes.map((fix) => ({
    lat: fix.lat,
    lon: fix.lon,
    altitude: 0,
    phase: fix.kind,
  }));
  const directPoints =
    from && to ? greatCircle(from, to).map(([lon, lat]) => ({ lat, lon, altitude: 0 })) : [];
  const points = fixPoints.length > 0 ? fixPoints : directPoints;

  return {
    points,
    waypoints: fixes,
    airports: [
      from ? { lat: from[1], lon: from[0], ident: departure.icao ?? null, kind: "sid" } : null,
      to ? { lat: to[1], lon: to[0], ident: arrival.icao ?? null, kind: "star" } : null,
    ].filter(Boolean),
  };
}
