/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */

import { LatLng } from "leaflet/dist/leaflet-src.esm";
import leaflet from "leaflet";
import rivets from "rivets";
import jQuery from "jquery";

import draw_base_map from "./base_map";

import { ACTUAL_ROUTE_COLOR } from "./config";

import request from "../request";

window.$ = jQuery;
window.jquery = jQuery;

// const geolib = require('geolib');

/**
 * Render the live map
 * @param _opts
 * @private
 */
export default (_opts) => {
  const opts = Object.assign(
    {
      center: [29.98139, -95.33374],
      refresh_interval: 10, // seconds
      zoom: 5,
      acars_uri: "/api/acars",
      update_uri: "/api/acars/geojson",
      pirep_uri: "/api/pireps/{id}",
      pirep_link_uri: "/pireps/{id}",
      positions: null,
      render_elem: "map",
      aircraft_icon: "/assets/img/acars/aircraft.png",
      flown_route_color: ACTUAL_ROUTE_COLOR,
      units: "nmi",
    },
    _opts
  );

  const map = draw_base_map(opts);
  const aircraftIcon = leaflet.icon({
    iconUrl: opts.aircraft_icon,
    iconSize: [42, 42],
    iconAnchor: [21, 21],
  });

  const centerCoords = new LatLng(opts.center[0], opts.center[1]);

  /**
   * Hold the markers
   * @type {{}}
   */
  const markers_list = {};
  let pannedToFlight = false;
  let layerFlights = null;
  let layerSelFlight = null;
  let layerSelFlightFeature = null;
  let layerSelFlightLayer = null;

  const liveMapController = {
    pirep: {},
    pireps: [],
    has_data: false,
    controller: {
      focusMarker: null, // assigned below
    },
  };

  rivets.bind($("#map-info-box"), liveMapController);
  rivets.bind($("#live_flights"), liveMapController);

  function drawRoute(feature, layer, route) {
    if (layerSelFlight !== null) {
      map.removeLayer(layerSelFlight);
    }

    layerSelFlight = new L.Geodesic([], {
      weight: 5,
      opacity: 0.9,
      color: opts.flown_route_color,
      wrap: false,
    }).addTo(map);

    layerSelFlight.fromGeoJson(route.line);
    layerSelFlightFeature = feature;
    layerSelFlightLayer = layer;

    // Center on it, but only do it once, in case the map is moved
    if (!pannedToFlight) {
      map.panTo({
        lat: route.position.lat,
        lng: route.position.lon,
      });

      pannedToFlight = true;
    }
  }

  /**
   * When a flight is clicked on, show the path, etc for that flight
   * @param feature
   * @param layer
   */
  function onFlightClick(feature, layer) {
    const pirep_uri = opts.pirep_uri.replace(
      "{id}",
      feature.properties.pirep_id
    );
    const geojson_uri = `${opts.pirep_uri.replace(
      "{id}",
      feature.properties.pirep_id
    )}/acars/geojson`;

    /*
     * Run these in parallel:
     * 1. Get information about the PIREP and populate the bottom box/container
     * 2. Draw out the flight route
     */
    request(pirep_uri).then((response) => {
      const pirep = response.data.data;
      console.log(pirep);

      liveMapController.pirep = pirep;
    });

    request(geojson_uri).then((response) => {
      const route = response.data.data;
      console.log(route);

      drawRoute(feature, layer, route);
    });
  }

  /**
   * Focus on a specific marker
   * @param e
   * @param model
   */
  function focusMarker(e, model) {
    if (!(model.pirep.id in markers_list)) {
      console.log("marker not found in list");
      return;
    }

    const marker = markers_list[model.pirep.id];
    onFlightClick(marker[0], marker[1]);
  }

  /*
   * Assign functions to the controller
   */
  liveMapController.controller.drawRoute = drawRoute;
  liveMapController.controller.focusMarker = focusMarker;
  liveMapController.controller.onFlightClick = onFlightClick;

  const updateMap = () => {
    request(opts.acars_uri).then((response) => {
      const pireps = response.data.data;
      liveMapController.pireps = pireps;
      liveMapController.has_data = pireps.length > 0;
    });

    request({ url: opts.update_uri }).then((response) => {
      const flightGeoJson = response.data.data;

      if (layerFlights !== null) {
        layerFlights.clearLayers();
      }

      layerFlights = leaflet.geoJSON(flightGeoJson, {
        onEachFeature: (feature, layer) => {
          layer.on({
            // eslint-disable-next-line no-unused-vars
            click: (e) => {
              pannedToFlight = false;
              liveMapController.controller.onFlightClick(feature, layer);
            },
          });

          let popup_html = "";
          if (
            feature.properties &&
            feature.properties.popup !== "" &&
            feature.properties.popup !== undefined
          ) {
            popup_html += feature.properties.popup;
            layer.bindPopup(popup_html);
          }

          // add to the list
          markers_list[feature.properties.pirep_id] = [feature, layer];
        },
        pointToLayer(feature, latlon) {
          return leaflet.marker(latlon, {
            icon: aircraftIcon,
            rotationAngle: feature.properties.heading,
          });
        },
      });

      layerFlights.addTo(map);

      // Reload the clicked-flight information
      if (layerSelFlight !== null) {
        liveMapController.controller.onFlightClick(
          layerSelFlightFeature,
          layerSelFlightLayer
        );
      } else {
        // Center on active flights
        // eslint-disable-next-line no-lonely-if
        if (!pannedToFlight) {
          try {
            map.panTo(layerFlights.getBounds().getCenter());
          } catch (e) {
            map.panTo(centerCoords);
          }
        }
      }
    });
  };

  updateMap();
  setInterval(updateMap, opts.refresh_interval * 1000);
};
