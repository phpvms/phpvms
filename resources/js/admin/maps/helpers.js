/**
 * Helpers shared between admin map renderers.
 */

import leaflet from "leaflet";

/**
 * Add a WMS layer to a map.
 *
 * @param {*} map  Leaflet map instance
 * @param {{ url: string, params: Object }} opts
 */
export function addWMSLayer(map, opts) {
  if (opts.url === "") {
    return null;
  }

  opts.params = Object.assign(
    {
      format: "image/png",
      transparent: true,
      maxZoom: 14,
      minZoom: 4,
    },
    opts.params,
  );

  const mlayer = leaflet.tileLayer.wms(opts.url, opts.params);
  mlayer.addTo(map);

  return mlayer;
}

/**
 * Bind a popup to a feature layer if the feature has popup HTML.
 */
export function showFeaturePopup(feature, layer) {
  let popup_html = "";
  if (feature.properties && feature.properties.popup) {
    popup_html += feature.properties.popup;
  }

  layer.bindPopup(popup_html);
}
