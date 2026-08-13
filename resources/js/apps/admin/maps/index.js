/**
 * Admin maps barrel.
 * Exposes leaflet globally (L) so geodesic / rotatedmarker plugins can extend it,
 * then re-exports the renderers used by admin blades.
 */

import L from "leaflet";

import "leaflet.geodesic";
import "leaflet-rotatedmarker";

import render_route_map from "./route_map";
import render_base_map from "./base_map";

window.L = L;

export { render_route_map, render_base_map };
