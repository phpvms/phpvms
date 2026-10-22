/**
 * Rendering tiers (design.md D13). Tier 0 is an ordinary maplibre `line` layer
 * with no three.js in its module graph; Tier 1 is the at-altitude custom layer,
 * loaded only on demand. The caller states its intent explicitly — the package
 * never infers a tier from element size.
 */
export type MapTier = 0 | 1;

export type MapTheme = "light" | "dark";

/**
 * A flown-track point at true altitude. `phase` is one of `App\Enums\PirepPhase`'s
 * string codes (e.g. `"ENR"`, `"TOF"`) — untyped here because that enum has no
 * `#[TypeScript]` type yet (map-api task 4.3). An unrecognised code renders in
 * the default route colour rather than failing (map-rendering spec, "Unknown
 * phase").
 */
export type RoutePoint = {
  lat: number;
  lon: number;
  /** Metres above the ellipsoid/ground, matching `getMatrixForModel`'s altitude unit. */
  altitude: number;
  phase?: string;
};

/**
 * A planned-route fix. Field names and nullability mirror the generated
 * `MapPlannedFixData` DTO (`resources/js/apps/fe-vue/src/types/generated.d.ts`)
 * structurally, same reasoning as `MapLayerConfig` below — `ident` and
 * `altitudeFt` are nullable there (an unnamed fix; an old archive or
 * non-SimBrief PIREP with no per-fix altitude, design.md D11).
 */
export type PlannedFix = {
  ident: string | null;
  lat: number;
  lon: number;
  altitudeFt: number | null;
  viaAirway?: string | null;
};

/**
 * One overlay layer (design.md D9 `map_layers`).
 *
 * Deliberately a package-local type rather than an import of fe-vue's generated
 * `MapConfigData` (`resources/js/apps/fe-vue/src/types/generated.d.ts`): per D1
 * the core has two adapters, one of them plain-JS admin with no generated
 * types, so the core cannot depend on either adapter's type source. Field names
 * match the generated DTO on purpose — a real `MapConfigData.layers[number]`
 * satisfies this structurally with no import, and the admin adapter builds the
 * same shape by hand from `window.filamentData.maps`.
 */
export type MapLayerConfig = {
  id: number;
  name: string;
  type: string;
  urlTemplate: string;
  attribution: string | null;
  minZoom: number;
  maxZoom: number;
  opacity: number;
  apiKey: string | null;
  surfaces: string[] | null;
};

/** Resolved basemap + overlay config, structurally matching the generated `MapConfigData` DTO. */
export type MapConfig = {
  basemapLight: string;
  basemapDark: string;
  customStyleUrl: string | null;
  customStyleApiKey: string | null;
  layers: MapLayerConfig[];
};

/**
 * Constructor options for `createMap()` (design.md D6): config is always a
 * parameter, never a module constant, from the first commit — even while the
 * values in it are still defaults.
 */
export type MapCreateOptions = {
  config: MapConfig;
  theme: MapTheme;
  tier: MapTier;
};

/** Ground position plus altitude, the shape every anchoring/chunking helper consumes. */
export type ScenePosition = {
  lon: number;
  lat: number;
  /** Metres. */
  altitude: number;
};
