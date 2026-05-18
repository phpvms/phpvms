/**
 * Builds a Leaflet map bound to opts.render_elem (DOM id).
 * Patched from resources/js/maps/base_map.js to honor opts.render_elem
 * instead of hardcoding the "map" id, so multiple maps can coexist on a page.
 *
 * Available providers: https://leaflet-extras.github.io/leaflet-providers/preview/
 */

import leaflet from "leaflet";
import "leaflet-providers";

export default (_opts) => {
  const opts = Object.assign(
    {
      render_elem: "map",
      center: [29.98139, -95.33374],
      zoom: 5,
      maxZoom: 10,
      layers: [],
      set_marker: false,
      leafletOptions: {},
    },
    _opts,
  );

  const leafletOptions = Object.assign(
    {
      center: opts.center,
      zoom: opts.zoom,
      scrollWheelZoom: false,
      providers: {},
    },
    opts.leafletOptions,
  );

  // Default tile provider if caller didn't specify one.
  if (Object.entries(leafletOptions.providers).length === 0) {
    leafletOptions.providers = {
      "Esri.WorldStreetMap": {},
    };
  }

  const map = leaflet.map(opts.render_elem, leafletOptions);

  // eslint-disable-next-line guard-for-in,no-restricted-syntax
  for (const key in leafletOptions.providers) {
    leaflet.tileLayer.provider(key, leafletOptions.providers[key]).addTo(map);
  }

  return map;
};
