import { computed, type ComputedRef } from "vue";
import { usePage } from "@inertiajs/vue3";
import { CARTO_DARK_MATTER_URL, CARTO_VOYAGER_URL, type MapTheme } from "@phpvms/map";
import { useTheme } from "@/shared/lib/useTheme";

/**
 * Reads the two things every `@phpvms/map` surface needs from app-wide state
 * (design.md D6 — config is always a constructor parameter, never fetched by
 * the map package itself):
 *
 * - `config`: `MapConfigData`, shared with every SPA page by
 *   `HandleInertiaRequests::share()` under the `map` key. A page that never
 *   renders a map still receives it (it's not `Inertia::lazy()`), so no
 *   surface needs to request it specially — this composable just reads what
 *   Inertia already delivered.
 * - `theme`: derived from the SAME light/dark toggle that drives the rest of
 *   the chrome (`useTheme`'s `isDark`, backed by `useDark`/`.dark` on
 *   `<html>`), so the map's basemap swaps in lockstep with the deck instead
 *   of reading its own separate preference.
 *
 * Falls back to the curated Carto pair with no overlay layers if `map` is
 * ever absent from page props (defensive only — the shared-props layer
 * always includes it today).
 */

interface MapContextPageProps extends Record<string, unknown> {
  map?: App.Http.Data.MapConfigData;
}

const FALLBACK_CONFIG: App.Http.Data.MapConfigData = {
  basemapLight: CARTO_VOYAGER_URL,
  basemapDark: CARTO_DARK_MATTER_URL,
  customStyleUrl: null,
  customStyleApiKey: null,
  layers: [],
};

export function useMapContext(): {
  config: ComputedRef<App.Http.Data.MapConfigData>;
  theme: ComputedRef<MapTheme>;
} {
  const page = usePage<MapContextPageProps>();
  const { isDark } = useTheme();

  const config = computed(() => page.props.map ?? FALLBACK_CONFIG);
  const theme = computed<MapTheme>(() => (isDark.value ? "dark" : "light"));

  return { config, theme };
}
