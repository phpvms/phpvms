/**
 * RouteForge entry point.
 *
 * Mounts the Preact `<App />` into `#routeforge-root` (rendered by the
 * Filament Blade view at resources/views/filament/pages/route-forge.blade.php).
 *
 * Fails visibly — not loudly — if the runtime invariants break:
 *
 *   - missing `#routeforge-root`: nothing to render into; the Blade view
 *     would have to be broken for this to happen, so we exit silently.
 *   - missing `window.routeforgeConfig`: the page template failed to inject
 *     config; render a diagnostic so the admin sees *something* instead of
 *     a blank pane.
 *
 * No console.* — oxlint keeps this file clean. Diagnostic surfaces in-page.
 */

import { render } from "preact";

import { App } from "./App";

const root = document.getElementById("routeforge-root");

if (root !== null) {
  if (window.routeforgeConfig === undefined) {
    root.textContent = "RouteForge config not injected (window.routeforgeConfig missing).";
  } else {
    render(<App />, root);
  }
}

export {};
