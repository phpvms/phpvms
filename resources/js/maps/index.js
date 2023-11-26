/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

import L from "leaflet";

import render_airspace_map from "./airspace_map";
import render_live_map from "./live_map";
import render_route_map from "./route_map";
import render_base_map from "./base_map";

import "leaflet.geodesic";
import "leaflet-rotatedmarker";

window.L = L;

export {
  render_airspace_map,
  render_live_map,
  render_route_map,
  render_base_map,
};
