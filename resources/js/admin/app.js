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

// The console rail is fixed collapsed on desktop; the collapse controls are
// removed. Pin Filament's persisted sidebar state before Alpine reads it.
// Filament's sidebar store (vendor/filament/filament/resources/js/stores/
// sidebar.js) persists via `window.Alpine.$persist(true).as('isOpenDesktop')`
// — Alpine's persist plugin uses the `.as()` alias as the raw localStorage
// key with no `_x_` prefix (that prefix only applies when `.as()` is NOT
// called), so the real key is `isOpenDesktop`, not `_x_isOpenDesktop`.
localStorage.setItem("isOpenDesktop", JSON.stringify(false));

import axios from "axios";

import config from "./config";
import request from "./request";
import Storage from "./storage";
import "./rail-nav";
import "./theme-picker";
import "./utc-clock";

window.axios = axios;

// Lazy-load the maps chunk on first call. Subsequent calls reuse the
// resolved module via the cached promise (ES module spec dedupes by URL).
// `import.meta.glob` (not a bare `import("./maps")`) is used so Vite rewrites
// the chunk URL correctly in dev AND build: a bare dynamic import becomes an
// absolute path that the browser resolves against the document origin, which
// 404s when the page is served behind a proxy different from the dev server.
let mapsModulePromise = null;
const loadMaps = () => {
  if (!mapsModulePromise) {
    mapsModulePromise = import.meta.glob("./maps/index.js")["./maps/index.js"]();
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

// Dashboard charts (D3) — loaded on every admin page; `init()` sets up a
// MutationObserver so widgets that Filament lazy-mounts (scroll-triggered
// hydration) still get rendered when they appear. Same `import.meta.glob`
// treatment as the maps chunk above (see that comment).
//
// NOTE: keep the EXACT shape of the maps lazy-load above — Vite's glob
// transform (and rolldown's build-time builtin) matches `import.meta.glob`
// calls literally; a multi-line call or a `.then()` chain on the result is
// not transformed (raw call reaches the browser → "import.meta.glob is not
// a function", and the chunk is dropped in build).
const loadDashboardCharts = () =>
  import.meta.glob("./dashboard/index.js")["./dashboard/index.js"]();
loadDashboardCharts()
  .then((m) => m.init())
  .catch((err) => console.error("[dashboard] failed to load charts", err));
