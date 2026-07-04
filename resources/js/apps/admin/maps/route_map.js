/**
 * Renders a PIREP route map (planned + actual route lines and points).
 *
 * Mirrors resources/js/maps/route_map.js but lives in the admin tree so the
 * admin bundle can evolve independently from the seven theme bundle.
 */

import leaflet from "leaflet";

import draw_base_map from "./base_map";
import { addWMSLayer } from "./helpers";
import { ACTUAL_ROUTE_COLOR, CIRCLE_COLOR, PLAN_ROUTE_COLOR } from "./config";

/**
 * Bind a popup to each route point feature when it has popup HTML.
 */
export const onFeaturePointClick = (feature, layer) => {
  let popup_html = "";
  if (feature.properties && feature.properties.popup) {
    popup_html += feature.properties.popup;
  }

  layer.bindPopup(popup_html);
};

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
    _opts,
  );

  const pointToLayer = (feature, latlng) =>
    leaflet.circleMarker(latlng, {
      radius: 5,
      fillColor: opts.circle_color,
      color: "#000",
      weight: 1,
      opacity: 1,
      fillOpacity: 0.8,
    });

  const map = draw_base_map(opts);

  if (opts.metar_wms.url !== "") {
    addWMSLayer(map, opts.metar_wms);
  }

  // Planned route line (great-circle).
  const plannedRouteLayer = new L.Geodesic([], {
    weight: 4,
    opacity: 0.9,
    color: opts.flightplan_route_color,
    steps: 50,
    wrap: false,
  }).addTo(map);

  if (opts.planned_route_line) {
    plannedRouteLayer.fromGeoJson(opts.planned_route_line);

    try {
      map.fitBounds(plannedRouteLayer.getBounds());
    } catch (e) {
      console.log(e);
    }
  }

  // Planned route waypoints.
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

  // Actual flown route.
  if (opts.actual_route_line !== null && opts.actual_route_line.features.length > 0) {
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

  if (opts.actual_route_points !== null && opts.actual_route_points.features.length > 0) {
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

  return map;
};
