/**
 * Admin Filament panel JavaScript entry.
 *
 * Built by Vite and injected into every admin page via the
 * PanelsRenderHook::HEAD_END render hook in AdminPanelProvider, which uses
 * `@vite('resources/js/apps/admin/app.js')` so manifest resolution only happens
 * at HTTP render time (never during console boot — see provider for full
 * rationale).
 *
 * The `@phpvms/map` maplibre package (~three.js on Tier 1) is loaded via
 * dynamic `import()` here, through `./phpvms-map.js`. Vite code-splits it
 * into its own chunk that the browser only fetches when an admin blade
 * actually calls `window.phpvms.map.render_pirep_map_from_api(...)` etc.
 * Admin pages without a map pay no cost beyond this thin entry.
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
import { currentMode } from "./theme-picker";
import "./utc-clock";
import "./autosave-indicator";

window.axios = axios;

// The @phpvms/map package's imperative adapter, re-exported locally by
// `./phpvms-map.js` (see that file for why: `import.meta.glob`'s pattern and
// object-key arguments must match exactly, and the package's own path is too
// long to keep that single-line after formatting — a wrapped call is NOT
// transformed, same trap the dashboard loader's own NOTE below warns about).
// `import.meta.glob` (not a bare `import("./phpvms-map.js")`) is used so
// Vite rewrites the chunk URL correctly in dev AND build: a bare dynamic
// import becomes an absolute path that the browser resolves against the
// document origin, which 404s when the page is served behind a proxy
// different from the dev server.
//
// This replaced the Leaflet-based `./maps/index.js` chunk (tasks.md 6.2/6.5)
// — that module and its `render_route_map`/`render_base_map` entries are
// gone, confirmed by grep with no remaining caller in resources/views.
let phpvmsMapModulePromise = null;
const loadPhpvmsMap = () => {
  if (!phpvmsMapModulePromise) {
    phpvmsMapModulePromise = import.meta.glob("./phpvms-map.js")["./phpvms-map.js"]();
  }

  return phpvmsMapModulePromise;
};

window.phpvms = {
  config,
  request,
  Storage,
  map: {
    render_pirep_map: async (...args) => {
      const phpvmsMap = await loadPhpvmsMap();

      return phpvmsMap.renderPirepMap(...args);
    },
    render_live_map: async (...args) => {
      const phpvmsMap = await loadPhpvmsMap();

      return phpvmsMap.renderLiveMap(...args);
    },
    // The PIREP detail globe and the live-flights globe (tasks.md 6.2/6.3)
    // fetch their own data client-side from GET api/map/pirep/{id} and GET
    // api/map/live rather than receiving server-injected features — these
    // wrap that fetch + the DTO-to-adapter-shape transform, kept in
    // ./phpvms-map.js alongside the raw adapter re-export above.
    render_pirep_map_from_api: async (...args) => {
      const phpvmsMap = await loadPhpvmsMap();

      return phpvmsMap.renderPirepMapFromApi(...args);
    },
    render_live_map_from_api: async (...args) => {
      const phpvmsMap = await loadPhpvmsMap();

      return phpvmsMap.renderLiveMapFromApi(...args);
    },
    // Used by the live map's poll loop to refresh marker positions on the
    // same handle `render_live_map_from_api` returned, without re-fetching
    // through a whole new renderLiveMap() call.
    fetch_live_flights: async (...args) => {
      const phpvmsMap = await loadPhpvmsMap();

      return phpvmsMap.fetchLiveFlights(...args);
    },
  },
  // `window.phpvms.theme.current()` — light/dark, per theme-picker.js. The
  // maplibre admin surfaces need this once at map construction and again on
  // `theme-changed`, since a basemap style is fixed at createMap() time.
  theme: {
    current: currentMode,
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
