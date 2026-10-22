import * as THREE from "three";
import type { RoutePoint } from "../src/types.ts";

/**
 * A realistic long-haul track: JFK → LHR, ~5,540 km great-circle, with a
 * climb/cruise/descent altitude profile over three phase segments.
 * Great-circle interpolation is spherical slerp on the unit sphere, not a
 * lon/lat lerp — a straight lon/lat interpolation is not a great circle and
 * would understate how many 20 km chunks a real long-haul route needs.
 *
 * Moved here from the gated spike (`spike/route-spike.ts`, now deleted) once
 * `route-line.ts` existed to draw it for real.
 */

const EARTH_RADIUS_M = 6_371_000;
const FEET_TO_METRES = 0.3048;

function toUnitVector(lat: number, lon: number): THREE.Vector3 {
  const latR = (lat * Math.PI) / 180;
  const lonR = (lon * Math.PI) / 180;
  return new THREE.Vector3(
    Math.cos(latR) * Math.cos(lonR),
    Math.sin(latR),
    Math.cos(latR) * Math.sin(lonR),
  );
}

function toLatLon(v: THREE.Vector3): { lat: number; lon: number } {
  return {
    lat: (Math.asin(v.y) * 180) / Math.PI,
    lon: (Math.atan2(v.z, v.x) * 180) / Math.PI,
  };
}

export function fixtureLongHaulRoute(pointCount = 620): Required<RoutePoint>[] {
  const departure = { lat: 40.6413, lon: -73.7781 }; // KJFK
  const arrival = { lat: 51.4706, lon: -0.4619 }; // EGLL
  const cruiseAltitudeM = 35_000 * FEET_TO_METRES;

  const a = toUnitVector(departure.lat, departure.lon);
  const b = toUnitVector(arrival.lat, arrival.lon);
  const angleBetween = a.angleTo(b);
  const totalDistanceM = angleBetween * EARTH_RADIUS_M;

  // Typical top-of-climb / top-of-descent distances for a widebody long-haul.
  const climbDistanceM = 150_000;
  const descentDistanceM = 180_000;

  return Array.from({ length: pointCount }, (_, i) => {
    const t = i / (pointCount - 1);
    const point = new THREE.Vector3();
    if (angleBetween > 0) {
      // Slerp: renormalize onto the sphere along the great-circle arc rather than the lerp's chord.
      const along = Math.sin((1 - t) * angleBetween) / Math.sin(angleBetween);
      const to = Math.sin(t * angleBetween) / Math.sin(angleBetween);
      point.copy(a).multiplyScalar(along).addScaledVector(b, to);
    } else {
      point.copy(a).lerp(b, t);
    }
    const { lat, lon } = toLatLon(point.normalize());

    const distanceM = t * totalDistanceM;
    let altitude: number;
    let phase: string;
    if (distanceM < climbDistanceM) {
      phase = "climb";
      altitude = cruiseAltitudeM * (distanceM / climbDistanceM);
    } else if (distanceM > totalDistanceM - descentDistanceM) {
      phase = "descent";
      altitude = cruiseAltitudeM * ((totalDistanceM - distanceM) / descentDistanceM);
    } else {
      phase = "cruise";
      altitude = cruiseAltitudeM;
    }

    return { lat, lon, altitude, phase };
  });
}
