/**
 * Deep-link prefill.
 *
 * The bundle page links into RouteForge with the tour already selected:
 *
 *   /admin/route-forge?topology=tour&bundle=12&bundle_name=Pacific+Tour&fresh=1
 *
 * `App\Filament\Pages\RouteForge::mount()` whitelists those params and renders
 * them as JSON on `#routeforge-root`'s `data-prefill` attribute;
 * `main.tsx` parses it here and applies it to the store before the first
 * render.
 *
 * Param contract (the URL builder on the bundle page must match):
 *
 *   topology     one of the five Topology values; anything else is dropped
 *   bundle       existing FlightBundle id, positive integer
 *   bundle_name  display name for the locked bundle summary; optional, and
 *                only a starting point — BundleConfigSection re-resolves the
 *                bundle from the server by searching this name
 *   fresh        "1" to discard any stored draft so a stale one cannot
 *                swallow the link
 *
 * Everything here is defensive: the params are URL text, so an unknown
 * topology or a junk bundle id is dropped rather than written into the form.
 */

import { applyTopology } from "../components/TopologyPicker";
import type { Form, Topology } from "../state/types";

const TOPOLOGIES: Topology[] = ["hub_spokes", "spokes_hub", "hub_and_spokes", "mesh", "tour"];

export type Prefill = {
  topology: Topology | null;
  bundle_id: number | null;
  bundle_name: string;
  /** Discard the stored draft instead of offering to resume it. */
  fresh: boolean;
};

/**
 * Parse the `data-prefill` attribute. Returns null when the attribute is
 * absent, unparseable, or carries nothing usable — callers then leave the
 * store at its defaults.
 */
export function parsePrefill(raw: string | undefined): Prefill | null {
  if (raw === undefined || raw === "") {
    return null;
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch {
    return null;
  }
  if (typeof parsed !== "object" || parsed === null) {
    return null;
  }

  const o = parsed as Record<string, unknown>;
  const topology =
    typeof o.topology === "string" && (TOPOLOGIES as string[]).includes(o.topology)
      ? (o.topology as Topology)
      : null;
  const bundleId =
    typeof o.bundle_id === "number" && Number.isInteger(o.bundle_id) && o.bundle_id > 0
      ? o.bundle_id
      : null;

  if (topology === null && bundleId === null) {
    return null;
  }

  return {
    topology,
    bundle_id: bundleId,
    bundle_name: typeof o.bundle_name === "string" ? o.bundle_name : "",
    fresh: o.fresh === true,
  };
}

/**
 * Fold a prefill into the form. Topology goes through `applyTopology` — the
 * same transition the picker runs — so a deep-linked tour derives `mode`,
 * `create_returns` and `flight_number_strategy` exactly as picking tour in
 * the UI would.
 */
export function applyPrefill(current: Form, prefill: Prefill): Form {
  const withTopology =
    prefill.topology === null ? current : applyTopology(current, prefill.topology);

  if (prefill.bundle_id === null) {
    return withTopology;
  }

  return {
    ...withTopology,
    bundle: {
      ...withTopology.bundle,
      existing_bundle_id: prefill.bundle_id,
      name: prefill.bundle_name,
    },
  };
}
