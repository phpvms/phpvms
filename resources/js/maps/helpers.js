/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

import leaflet from "leaflet";

/**
 * Add a WMS layer to a map. opts must be:
 * {
 *  url: '',
 *  params: {}
 *  }
 * @param map
 * @param opts
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
    opts.params
  );

  const mlayer = leaflet.tileLayer.wms(opts.url, opts.params);

  mlayer.addTo(map);

  return mlayer;
}

/**
 * Show a popup
 * @param feature
 * @param layer
 */
export function showFeaturePopup(feature, layer) {
  let popup_html = "";
  if (feature.properties && feature.properties.popup) {
    popup_html += feature.properties.popup;
  }

  layer.bindPopup(popup_html);
}
