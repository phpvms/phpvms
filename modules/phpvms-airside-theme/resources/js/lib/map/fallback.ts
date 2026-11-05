/**
 * The no-WebGL2 static fallback (design.md D16, tasks.md 2b.6): a route
 * summary and airport pair, not an error string or a blank container. Pure
 * DOM — no maplibre, no three.js, so it costs nothing extra to ship
 * alongside `supportsWebGL2()` (`browser.ts`) as the thing every map surface
 * falls back to when that check fails.
 */

export type FallbackAirport = {
  icao: string;
  name?: string;
};

export type FallbackRouteSummary = {
  from: FallbackAirport;
  to: FallbackAirport;
  /** Short human summary, e.g. flight number or a distance string. Optional — the airport pair alone is the floor. */
  summary?: string;
};

const CONTAINER_CLASS = "phpvms-map-fallback";

/**
 * Render the static fallback into `container`, replacing any existing
 * content. Framework-agnostic (design.md D1) — this is plain DOM, not a Vue
 * component, so both adapters can call it identically.
 */
export function renderStaticFallback(container: HTMLElement, route: FallbackRouteSummary): void {
  container.replaceChildren();
  container.classList.add(CONTAINER_CLASS);

  const pair = document.createElement("div");
  pair.className = `${CONTAINER_CLASS}__route`;
  pair.textContent = `${route.from.icao} → ${route.to.icao}`;
  container.appendChild(pair);

  if (route.summary) {
    const summary = document.createElement("div");
    summary.className = `${CONTAINER_CLASS}__summary`;
    summary.textContent = route.summary;
    container.appendChild(summary);
  }
}
