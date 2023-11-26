/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

import leaflet from "leaflet";
import draw_base_map from "./base_map";
import { addWMSLayer } from "./helpers";
import request from "../request";

import { ACTUAL_ROUTE_COLOR, CIRCLE_COLOR, PLAN_ROUTE_COLOR } from "./config";

/**
 * Show some popup text when a feature is clicked on
 * @param feature
 * @param layer
 */
export const onFeaturePointClick = (feature, layer) => {
  let popup_html = "";
  if (feature.properties && feature.properties.popup) {
    popup_html += feature.properties.popup;
  }

  layer.bindPopup(popup_html);
};

/**
 *
 * @param _opts
 * @private
 */
export default (_opts) => {
  const opts = Object.assign(
    {
      route_points: null,
      planned_route_line: null,
      actual_route_points: null,
      actual_route_line: null,
      render_elem: "map",
      live_map: false,
      aircraft_icon: "/assets/img/acars/aircraft.png",
      refresh_interval: 10,
      flown_route_color: ACTUAL_ROUTE_COLOR,
      circle_color: CIRCLE_COLOR,
      flightplan_route_color: PLAN_ROUTE_COLOR,
      metar_wms: {
        url: "",
        params: {},
      },
    },
    _opts
  );

  /**
   * Show each point as a marker
   * @param feature
   * @param latlng
   * @returns {*}
   */
  const pointToLayer = (feature, latlng) =>
    leaflet.circleMarker(latlng, {
      radius: 5,
      fillColor: opts.circle_color,
      color: "#000",
      weight: 1,
      opacity: 1,
      fillOpacity: 0.8,
    });

  const aircraftIcon = leaflet.icon({
    iconUrl: opts.aircraft_icon,
    iconSize: [42, 42],
    iconAnchor: [21, 21],
  });

  const map = draw_base_map(opts);
  let layerLiveFlight;

  if (opts.metar_wms.url !== "") {
    addWMSLayer(map, opts.metar_wms);
  }

  const plannedRouteLayer = new L.Geodesic([], {
    weight: 4,
    opacity: 0.9,
    color: opts.flightplan_route_color,
    steps: 50,
    wrap: false,
  }).addTo(map);

  plannedRouteLayer.fromGeoJson(opts.planned_route_line);

  try {
    map.fitBounds(plannedRouteLayer.getBounds());
  } catch (e) {
    console.log(e);
  }

  // Draw the route points after
  if (opts.route_points !== null) {
    const route_points = leaflet.geoJSON(opts.route_points, {
      onEachFeature: onFeaturePointClick,
      pointToLayer,
      style: {
        color: opts.flightplan_route_color,
        weight: 3,
        opacity: 0.65,
      },
    });

    route_points.addTo(map);
  }

  /**
   * draw the actual route
   */

  if (
    opts.actual_route_line !== null &&
    opts.actual_route_line.features.length > 0
  ) {
    const actualRouteLayer = new L.Geodesic([], {
      weight: 3,
      opacity: 0.9,
      color: opts.flown_route_color,
      steps: 50,
      wrap: false,
    }).addTo(map);

    actualRouteLayer.fromGeoJson(opts.actual_route_line);

    try {
      map.fitBounds(actualRouteLayer.getBounds());
    } catch (e) {
      console.log(e);
    }
  }

  if (
    opts.actual_route_points !== null &&
    opts.actual_route_points.features.length > 0
  ) {
    const route_points = leaflet.geoJSON(opts.actual_route_points, {
      onEachFeature: onFeaturePointClick,
      pointToLayer,
      style: {
        color: opts.flown_route_color,
        weight: 3,
        opacity: 0.65,
      },
    });

    route_points.addTo(map);
  }

  /**
   *
   */
  /*
  const liveFlight = () => {
    request({ url: opts.pirep_uri }).then((response) => {
      const routeJson = response.data.data;
      console.log(routeJson);
      layerLiveFlight = leaflet.geoJSON(routeJson, {
        pointToLayer(feature, latlon) {
          return leaflet.marker(latlon, {
            icon: aircraftIcon,
            rotationAngle: feature.properties.heading,
          });
        },
      });

      layerLiveFlight.addTo(map);
    });
  };

  setInterval(liveFlight, opts.refresh_interval * 1000);
  */
};
