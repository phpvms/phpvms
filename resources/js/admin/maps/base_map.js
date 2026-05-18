/**
 * Builds a Leaflet map bound to opts.render_elem (DOM id).
 * Patched from resources/js/maps/base_map.js to honor opts.render_elem
 * instead of hardcoding the "map" id, so multiple maps can coexist on a page.
 *
 * Default tile stack:
 *   - CartoDB Voyager — neutral base, clean labels, free, no key.
 *   - OpenAIP airspace + nav-aid overlay — added on top when a key is
 *     present in window.filamentData.maps.openaip_api_key.
 *
 * Available providers (for base layers):
 *   https://leaflet-extras.github.io/leaflet-providers/preview/
 */

import leaflet from "leaflet";
import "leaflet-providers";

const OPENAIP_TILE_URL =
  "https://api.tiles.openaip.net/api/data/openaip/{z}/{x}/{y}.png?apiKey={apiKey}";
const OPENAIP_ATTRIBUTION =
  '<a href="https://www.openaip.net/" target="_blank">OpenAIP</a> — airspace data CC BY-NC-SA';

function addOpenAipOverlay(map) {
  const apiKey = window.filamentData?.maps?.openaip_api_key;
  if (!apiKey) return;

  leaflet
    .tileLayer(OPENAIP_TILE_URL, {
      apiKey,
      attribution: OPENAIP_ATTRIBUTION,
      maxZoom: 14,
      minZoom: 4,
      opacity: 0.9,
    })
    .addTo(map);
}

export default (_opts) => {
  const opts = Object.assign(
    {
      render_elem: "map",
      center: [29.98139, -95.33374],
      zoom: 5,
      maxZoom: 14,
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

  // Default tile provider if caller didn't specify one. CartoDB Voyager is a
  // neutral, label-light base with stronger geographic features than Positron
  // — better contrast for the OpenAIP airspace overlay.
  if (Object.entries(leafletOptions.providers).length === 0) {
    leafletOptions.providers = {
      "CartoDB.Voyager": {},
    };
  }

  const map = leaflet.map(opts.render_elem, leafletOptions);

  // eslint-disable-next-line guard-for-in,no-restricted-syntax
  for (const key in leafletOptions.providers) {
    leaflet.tileLayer.provider(key, leafletOptions.providers[key]).addTo(map);
  }

  addOpenAipOverlay(map);

  return map;
};
