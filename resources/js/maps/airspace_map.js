/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

import leaflet from "leaflet";
import draw_base_map from "./base_map";
import { addWMSLayer } from "./helpers";

/**
 * Render a map with the airspace, etc around a given set of coords
 * e.g, the airport map
 * @param {Object} _opts
 */
export default (_opts) => {
  const opts = Object.assign(
    {
      render_elem: "map",
      overlay_elem: "",
      lat: 0,
      lon: 0,
      zoom: 12,
      layers: [],
      set_marker: true,
      marker_popup: "",

      // Passed from the config/maps.php file
      metar_wms: {
        url: "",
        params: {},
      },
    },
    _opts
  );

  const map = draw_base_map(opts);
  const coords = [opts.lat, opts.lon];
  console.log("Applying coords", coords);

  map.setView(coords, opts.zoom);
  if (opts.set_marker === true) {
    leaflet.marker(coords).addTo(map).bindPopup(opts.marker_popup);
  }

  if (opts.metar_wms.url !== "") {
    addWMSLayer(map, opts.metar_wms);
  }

  return map;
};
