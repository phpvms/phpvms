import type { StyleSpecification } from "maplibre-gl";
import type { MapTheme } from "./types.ts";

/**
 * The curated, key-free basemap list (design.md D8), proven by
 * `../acars/.../map-basemaps.ts` and matching the exact values
 * `App\Filament\Pages\Settings::basemapCuratedOptions()` stores in
 * `map.basemap_light`/`map.basemap_dark` — these string literals are a
 * contract with that admin page, not a local choice.
 */
export const CARTO_VOYAGER_URL =
  "https://basemaps.cartocdn.com/gl/voyager-nolabels-gl-style/style.json";
export const CARTO_DARK_MATTER_URL =
  "https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json";

/**
 * vaCentral's own vector tiles — the first curated basemap that isn't a third-party dependency
 * (D8 otherwise justifies this list purely on "all key-free"; this one is additionally
 * first-party, phpVMS's own tile service). Ordinary style URLs, resolved through the same
 * `style.ts` fetch path as the Carto ones above — no sentinel needed.
 *
 * Both styles were fetched and confirmed valid `StyleSpecification` documents (`version: 8`,
 * `sources`, 76 `layers`) before adding these constants. Unlike the hand-built `esriWorldImageryStyle`
 * below, BOTH carry their OWN `glyphs` URL (`https://tiles.vacentral.net/fonts/{fontstack}/{range}.pbf`,
 * confirmed reachable for the style's actual referenced font stack, `ManropeRegular-16` — labelled
 * `symbol` layers, e.g. `places_country`, will render text, not silently nothing per D8's warning).
 * The exact URL strings must match `App\Filament\Pages\Settings::basemapCuratedOptions()`
 * byte-for-byte, same contract as the Carto ones — a mismatch would silently fall through to the
 * "custom URL" branch in `style.ts`'s `resolveStyle()`.
 */
/**
 * Mapterhorn's global DEM, as a TileJSON URL — the same source MapLibre's own
 * `3d-terrain` example uses. Key-free, `terrarium` encoding, 512 px WebP tiles
 * (verified by fetching the TileJSON: global bounds, `"encoding": "terrarium"`,
 * `"tileSize": 512`).
 *
 * Chosen over AWS's `elevation-tiles-prod`, which is also key-free terrarium but
 * serves 256 px PNG — four times the tile requests to cover the same area, in a
 * heavier codec. `raster-dem` takes the TileJSON `url` directly, so encoding and
 * tile size come from the service rather than being restated (and drifting) here.
 */
export const MAPTERHORN_TERRAIN_URL = "https://tiles.mapterhorn.com/tilejson.json";

export const VACENTRAL_LIGHT_URL = "https://tiles.vacentral.net/styles/light/style.json";
export const VACENTRAL_DARK_URL = "https://tiles.vacentral.net/styles/dark/style.json";

/**
 * NOT a fetchable URL — the sentinel `Settings::MAP_BASEMAP_ESRI` stores
 * verbatim in `MapConfigData.basemapLight`/`basemapDark` when the operator
 * picks "ESRI World Imagery". `style.ts` recognises this exact literal and
 * resolves it to `esriWorldImageryStyle()` below rather than fetching it.
 */
export const ESRI_SENTINEL = "esri-world-imagery";

/**
 * A flat single-colour style — the fallback when a remote style fetch fails
 * (offline, CDN down). Without this, `style.ts` would have to reject and
 * leave `createMap` with nothing to construct a `Map` from (design.md D8,
 * D16: "the surface shows a useful static fallback ... rather than a blank
 * container").
 */
export function blankStyle(name: string, backgroundColor: string): StyleSpecification {
  return {
    version: 8,
    name,
    sources: {},
    layers: [
      { id: "background", type: "background", paint: { "background-color": backgroundColor } },
    ],
  };
}

/**
 * ESRI World Imagery (satellite), hand-built and bundled — ported from
 * `../acars/.../map-basemaps.ts` `basemapSatellite()`.
 *
 * `background` is the globe's sky (design.md D8): unlike the Carto styles,
 * whose own JSON already varies light/dark, a hand-built style must be
 * handed a `background-color` explicitly or the globe reads permanently
 * dark regardless of the resolved theme.
 *
 * NO `glyphs` URL: acars points this at its own self-hosted SDF glyph
 * directory (`/fonts/{fontstack}/{range}.pbf`), which phpVMS does not have.
 * Harmless today — this style's own layer list has no `symbol` layer that
 * would need one — but flagged here because a FUTURE `symbol` layer drawn
 * over this style (e.g. D5's aircraft markers, if they ever need TEXT
 * rather than an icon) would silently render nothing without one. Per D8's
 * own warning, glyphs belong ONLY on a bundled hand-built style, never
 * bolted onto a third-party one like the Carto styles below.
 */
export function esriWorldImageryStyle(theme: MapTheme): StyleSpecification {
  return {
    version: 8,
    name: ESRI_SENTINEL,
    sources: {
      esri: {
        type: "raster",
        tiles: [
          "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        ],
        tileSize: 256,
        attribution: "Tiles © Esri — Source: Esri, Maxar, Earthstar Geographics",
        maxzoom: 19,
      },
    },
    layers: [
      {
        id: "background",
        type: "background",
        paint: { "background-color": theme === "dark" ? "#000000" : "#dce6ec" },
      },
      { id: "esri-raster", type: "raster", source: "esri", paint: { "raster-opacity": 1 } },
    ],
  };
}
