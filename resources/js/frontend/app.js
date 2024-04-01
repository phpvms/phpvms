/**
 * Before you edit these, read the documentation on how these files are compiled:
 * https://docs.phpvms.net/developers/building-assets
 *
 * Edits here don't take place until you compile these assets and then upload them.
 */
import moment from "moment";
import popper from "popper.js";
import bootstrap from "bootstrap";
import jQuery from "jquery";

// Import the bids functionality
import { addBid, removeBid } from "./bids";
import handleExternalRedirects from "./external_redirect";

// import '../entrypoint';

// Import the mapping function
import {
  render_airspace_map,
  render_base_map,
  render_live_map,
  render_route_map,
} from "../maps/index";

window.phpvms.bids = {
  addBid,
  removeBid,
};

window.phpvms.map = {
  render_airspace_map,
  render_base_map,
  render_live_map,
  render_route_map,
};

window.bootstrap = bootstrap;
window.moment = moment;
window.popper = popper;
window.$ = jQuery;

// External redirects handler
handleExternalRedirects();
