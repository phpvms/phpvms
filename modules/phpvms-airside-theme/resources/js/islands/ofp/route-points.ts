import { greatCircle } from "../flights/geo";
import type { LonLat } from "../flights/geo";
import type { RouteLegKind, RoutePoint, RouteWaypoint } from "@airside/lib/map";

/**
 * Pure route geometry for the briefing globe: navlog legs, their SID/STAR/enroute
 * labels and the line segments to draw. Coordinates are [lon, lat].
 */

/** Two points are the same place if they agree to about 1 km. */
const SAME_PLACE_DEGREES = 0.01;

interface Airport {
  lat: number | null;
  lon: number | null;
  icao?: string | null;
}

interface PlannedFix {
  lat: number;
  lon: number;
  ident: string | null;
  viaAirway?: string | null;
  isSidStar?: boolean;
}

function isSamePlace(point: { lat: number; lon: number }, airport: LonLat): boolean {
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
export function classifyFixes(fixes: PlannedFix[]): Required<RouteWaypoint>[] {
  if (fixes.length === 0) return [];

  const runs: PlannedFix[][] = [];
  for (const fix of fixes) {
    const current = runs.at(-1);
    if (current && current[0].viaAirway === fix.viaAirway) current.push(fix);
    else runs.push([fix]);
  }

  const kinds: RouteLegKind[] = runs.map(() => "enroute");
  if (runs[0].some((fix) => fix.isSidStar)) kinds[0] = "sid";
  if (runs.length > 1 && runs.at(-1)!.some((fix) => fix.isSidStar)) kinds[kinds.length - 1] = "star";

  return runs.flatMap((run, index) =>
    run.map((fix) => ({ lat: fix.lat, lon: fix.lon, ident: fix.ident, kind: kinds[index] })),
  );
}

function pointOf(airport: Airport | null | undefined): LonLat | null {
  return airport?.lat != null && airport.lon != null ? [airport.lon, airport.lat] : null;
}

/**
 * The navlog's classified fixes bounded by the airports. SimBrief's navlog ends
 * on the destination but starts on the first climb waypoint, so each end is
 * added only when the navlog does not already reach it.
 */
export function routeFixes(
  plannedFixes: PlannedFix[],
  departure: Airport | null | undefined,
  arrival: Airport | null | undefined,
): Required<RouteWaypoint>[] {
  const fixes = classifyFixes(plannedFixes);
  if (fixes.length === 0) return fixes;

  const from = pointOf(departure);
  if (from && !isSamePlace(fixes[0], from)) {
    fixes.unshift({
      lat: from[1],
      lon: from[0],
      ident: departure?.icao ?? null,
      kind: fixes[0].kind,
    });
  }

  const to = pointOf(arrival);
  const last = fixes.at(-1);
  if (to && last && !isSamePlace(last, to)) {
    fixes.push({ lat: to[1], lon: to[0], ident: arrival?.icao ?? null, kind: last.kind });
  }

  return fixes;
}

/**
 * Everything the globe draws: `points` for the line (the navlog, else a great
 * circle between the airports), `waypoints` for the dots and labels (navlog
 * only) and `airports` for the two endpoints.
 */
export function routeGeometry({
  plannedFixes,
  departure,
  arrival,
}: {
  plannedFixes?: PlannedFix[] | null;
  departure?: Airport | null;
  arrival?: Airport | null;
}) {
  const fixes = routeFixes(plannedFixes ?? [], departure, arrival);
  const from = pointOf(departure);
  const to = pointOf(arrival);

  const fixPoints: RoutePoint[] = fixes.map((fix) => ({
    lat: fix.lat,
    lon: fix.lon,
    altitude: 0,
    phase: fix.kind,
  }));
  const directPoints: RoutePoint[] =
    from && to ? greatCircle(from, to).map(([lon, lat]) => ({ lat, lon, altitude: 0 })) : [];
  const points = fixPoints.length > 0 ? fixPoints : directPoints;

  return {
    points,
    waypoints: fixes,
    airports: [
      from ? { lat: from[1], lon: from[0], ident: departure?.icao ?? null, kind: "sid" as const } : null,
      to ? { lat: to[1], lon: to[0], ident: arrival?.icao ?? null, kind: "star" as const } : null,
    ].filter((airport) => airport !== null),
  };
}
