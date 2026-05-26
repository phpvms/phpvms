/**
 * RouteForge entry point.
 *
 * Boot flow (replaces the legacy `window.routeforgeConfig` global):
 *
 *   1. Locate `#routeforge-root` (rendered by the Filament Blade view).
 *      Missing → render diagnostic, abort. Indicates a broken Blade view.
 *
 *   2. Fetch `/admin/route-forge/api/boot` via `getBoot()`. The URL itself
 *      lives on the mount element's `data-boot-url` attribute so route
 *      prefix changes flow through Laravel's URL generator without a JS
 *      rebuild.
 *
 *   3. On success: hydrate the in-memory store with the envelope, then
 *      mount `<App />`. The store hydration MUST precede the App render
 *      because every component reads CSRF + routes + translations through
 *      `getBootOrThrow()`.
 *
 *   4. On failure (non-2xx, network error, bad URL): render `<BootError>`
 *      with a Retry button that re-runs the fetch. SPA never fails silently.
 *
 * Fail visibly — not loudly: no `console.*` (oxlint enforces). Diagnostics
 * surface in-page so admins see the cause without opening DevTools.
 */

import { render } from "preact";

import { App } from "./App";
import { BootError } from "./components/BootError";
import { ApiError, getBoot } from "./lib/api";
import { hydrateBoot } from "./state/boot";

const root = document.getElementById("routeforge-root");

if (root !== null) {
  void bootstrap(root);
}

async function bootstrap(mount: HTMLElement): Promise<void> {
  // Initial loading state. Plain text instead of a styled spinner — the
  // boot fetch is sub-300ms on a healthy connection and a heavier loader
  // would flash unpleasantly.
  mount.textContent = "Loading RouteForge…";

  try {
    const envelope = await getBoot();
    hydrateBoot(envelope);
    mount.textContent = "";
    render(<App />, mount);
  } catch (err) {
    const message = formatBootError(err);
    render(<BootError message={message} onRetry={() => bootstrap(mount)} />, mount);
  }
}

function formatBootError(err: unknown): string {
  if (err instanceof ApiError) {
    return `RouteForge boot endpoint returned HTTP ${err.status}. Reload the page or contact an administrator if this persists.`;
  }
  if (err instanceof Error) {
    return err.message;
  }
  return "RouteForge failed to load. Check the network tab and reload.";
}

export {};
