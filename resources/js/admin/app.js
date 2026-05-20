/**
 * Admin Filament panel JavaScript entry.
 *
 * Built by Vite and injected into every admin page via the
 * PanelsRenderHook::HEAD_END render hook in AdminPanelProvider, which uses
 * `@vite('resources/js/admin/app.js')` so manifest resolution only happens
 * at HTTP render time (never during console boot — see provider for full
 * rationale).
 *
 * The `maps` module statically imports Leaflet + its plugins (~150kB), so
 * it's loaded via dynamic `import()` here. Vite code-splits it into its own
 * chunk that the browser only fetches when an admin blade actually calls
 * `window.phpvms.map.render_route_map(...)` etc. Admin pages without a map
 * pay no cost beyond this thin entry.
 */

import axios from "axios";

import config from "./config";
import request from "./request";
import Storage from "./storage";

window.axios = axios;

// Lazy-load the maps chunk on first call. Subsequent calls reuse the
// resolved module via the cached promise (ES module spec dedupes by URL).
let mapsModulePromise = null;
const loadMaps = () => {
  if (!mapsModulePromise) {
    mapsModulePromise = import("./maps");
  }

  return mapsModulePromise;
};

window.phpvms = {
  config,
  request,
  Storage,
  map: {
    render_route_map: async (...args) => {
      const maps = await loadMaps();

      return maps.render_route_map(...args);
    },
    render_base_map: async (...args) => {
      const maps = await loadMaps();

      return maps.render_base_map(...args);
    },
  },
};

// Signal readiness for blade init scripts that race the ES module load.
// `@vite` injects this file as `<script type="module">`, which defers
// execution until after DOM parsing. Alpine's x-data init() fires on
// DOMContentLoaded — which can land before this module finishes executing
// on some browsers. Blades that need `window.phpvms` should await this
// promise inside init() rather than touching `window.phpvms` directly.
window.phpvmsReady = Promise.resolve(window.phpvms);
window.dispatchEvent(new CustomEvent("phpvms:ready", { detail: window.phpvms }));
