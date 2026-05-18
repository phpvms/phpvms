/**
 * Admin Filament panel JavaScript entry.
 *
 * Built by Vite, registered with FilamentAsset in AdminPanelProvider, and
 * lazy-loaded (loadedOnRequest) so it is only pulled in when an admin blade
 * opts in via x-load-js. Exposes a `window.phpvms` namespace mirroring the
 * legacy frontend bundle so blade init scripts can call
 * `phpvms.map.render_route_map(...)` etc.
 */

import axios from "axios";

import config from "./config";
import request from "./request";
import Storage from "./storage";
import * as maps from "./maps";

window.axios = axios;
window.phpvms = {
  config,
  request,
  Storage,
  map: maps,
};
