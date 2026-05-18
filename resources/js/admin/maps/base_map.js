/**
 * Builds a Leaflet map bound to opts.render_elem (DOM id).
 * Patched from resources/js/maps/base_map.js to honor opts.render_elem
 * instead of hardcoding the "map" id, so multiple maps can coexist on a page.
 *
 * Default tile stack:
 *   - CartoDB Voyager (light) / CartoDB.DarkMatter (dark) — neutral base,
 *     clean labels, free, no key.
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

const TILE_PROVIDERS = {
  light: "CartoDB.Voyager",
  dark: "CartoDB.DarkMatter",
};

function resolveTheme(theme) {
  if (theme === "system") {
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  }
  return theme;
}

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

  // Default tile provider if caller didn't specify one.
  const hasCustomProvider = Object.entries(leafletOptions.providers).length > 0;
  if (!hasCustomProvider) {
    const initialTheme =
      window.Alpine?.store("theme") ??
      (document.documentElement.classList.contains("dark") ? "dark" : "light");
    leafletOptions.providers = {
      [TILE_PROVIDERS[resolveTheme(initialTheme)]]: {},
    };
  }

  const map = leaflet.map(opts.render_elem, leafletOptions);

  // eslint-disable-next-line guard-for-in,no-restricted-syntax
  for (const key in leafletOptions.providers) {
    leaflet.tileLayer.provider(key, leafletOptions.providers[key]).addTo(map);
  }

  addOpenAipOverlay(map);

  // Swap base tile layer when Filament's theme changes.
  // Only applies when we own the provider (no custom leafletOptions.providers).
  if (!hasCustomProvider) {
    let baseTileLayer = null;
    let openAipLayer = null;
    // Grab the tile layers we added.
    map.eachLayer((layer) => {
      if (layer instanceof leaflet.TileLayer) {
        if (!baseTileLayer) {
          baseTileLayer = layer;
        } else {
          openAipLayer = layer;
        }
      }
    });

    window.addEventListener("theme-changed", (event) => {
      const theme = resolveTheme(event.detail);
      const newProvider = TILE_PROVIDERS[theme];
      if (!newProvider || !baseTileLayer) return;

      const newTileLayer = leaflet.tileLayer.provider(newProvider);
      map.removeLayer(baseTileLayer);
      newTileLayer.addTo(map);
      // Re-add OpenAIP overlay on top if it exists.
      if (openAipLayer) {
        map.removeLayer(openAipLayer);
        openAipLayer.addTo(map);
      }
      baseTileLayer = newTileLayer;
    });
  }

  return map;
};
