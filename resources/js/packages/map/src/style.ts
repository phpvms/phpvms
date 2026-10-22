import type { StyleSpecification } from "maplibre-gl";
import {
  blankStyle,
  CARTO_DARK_MATTER_URL,
  CARTO_VOYAGER_URL,
  esriWorldImageryStyle,
  ESRI_SENTINEL,
  VACENTRAL_DARK_URL,
  VACENTRAL_LIGHT_URL,
} from "./basemaps.ts";
import type { MapConfig, MapTheme } from "./types.ts";

/** Every curated URL — not `ESRI_SENTINEL` (not a URL) or an operator's custom style URL. */
const CURATED_URLS: readonly string[] = [
  CARTO_VOYAGER_URL,
  CARTO_DARK_MATTER_URL,
  VACENTRAL_LIGHT_URL,
  VACENTRAL_DARK_URL,
];

/**
 * Resolve a style from config (design.md D8/D6, tasks.md 3.3).
 *
 * LAZY on purpose — called from `createMap()`, never awaited at module
 * scope. `../acars/.../map-basemaps.ts` has a top-level `await fetchStyle(...)`
 * for its dark-matter basemap, which is exactly the anti-pattern to avoid: a
 * rejected fetch there throws out of module evaluation and breaks the whole
 * bundle's import rather than degrading one map (design.md D8's
 * `vite.config.ts:327` citation — ES2017 has no top-level-await form either,
 * an independent reason it cannot appear here).
 */

async function fetchStyle(url: string, fallback: StyleSpecification): Promise<StyleSpecification> {
  try {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);
    return (await response.json()) as StyleSpecification;
  } catch (err) {
    console.warn(
      `@phpvms/map: failed to load basemap style ${url}, falling back to a flat colour —`,
      err,
    );
    return fallback;
  }
}

const FALLBACK_BACKGROUND: Record<MapTheme, string> = { light: "#eaeaea", dark: "#1a1a1a" };

/**
 * Resolve `config.basemapLight`/`basemapDark` (whichever `theme` selects) to
 * a fetchable `StyleSpecification`.
 *
 * The value is one of three things, per the admin `Settings` page's own
 * contract (never a fourth `__custom__` sentinel — that gets swapped for
 * `customStyleUrl`'s value on save, so it never reaches here):
 * - `ESRI_SENTINEL` — not a URL, resolved locally with no fetch.
 * - one of the curated URLs (`CURATED_URLS` — the two Carto ones, or vaCentral's own light/dark
 *   vector tiles) — fetched, `blankStyle` fallback on failure.
 * - anything else — the operator's own custom style URL. Fetched as-is; per
 *   `TestMapStyleAction`'s own behaviour (which tests this exact URL with no
 *   key injected), `customStyleApiKey` is NOT appended to it — the operator's
 *   URL is expected to already carry whatever auth it needs, the same
 *   assumption the admin's own "Test style" action makes. `customStyleApiKey`
 *   is exposed on `MapConfig` for a caller that wants a different injection
 *   strategy (e.g. `map_layers.url_template`'s own `{apiKey}` convention),
 *   not applied here — this is an explicit choice made against observed
 *   behaviour elsewhere in the codebase, not a guess.
 */
export async function resolveStyle(
  config: MapConfig,
  theme: MapTheme,
): Promise<StyleSpecification> {
  const value = theme === "light" ? config.basemapLight : config.basemapDark;

  if (value === ESRI_SENTINEL) return esriWorldImageryStyle(theme);

  const fallback = blankStyle(
    `${CURATED_URLS.includes(value) ? "curated" : "custom"}-fallback`,
    FALLBACK_BACKGROUND[theme],
  );
  return fetchStyle(value, fallback);
}
