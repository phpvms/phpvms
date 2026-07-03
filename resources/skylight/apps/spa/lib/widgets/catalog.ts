/**
 * Dashboard widget CATALOG — the headless, extensible layer (what CAN be placed).
 *
 * First-party widgets register here; addons call `registerWidget()`. This module
 * has ZERO Vue imports so the catalog stays serializable — the actual Vue
 * component is resolved by NAME via a resolver map (components/widgets), exactly
 * like the slot registry. The per-user LAYOUT (what IS placed + where) lives in
 * useDashboardLayout; this is only the menu of available widgets.
 */

export type WidgetZone = 'grid' | 'sidebar'

export interface WidgetDef {
  /** Unique widget id (also the instance id — one per type for now). */
  id: string
  /**
   * Widget kind. `vue` (default) resolves to a bundled/async Vue component;
   * `blade` renders a server-rendered host shell (BladeWidget).
   */
  kind?: 'vue' | 'blade'
  /** Resolver key → Vue component (in components/widgets). Used by `vue` kind. */
  component?: string
  /** Runtime ESM URL for a `vue`-kind widget (e.g. /ext/foo/widget.js). */
  module?: string
  /** Props passed to the resolved `vue`-kind component. */
  props?: Record<string, unknown>
  /** Server endpoint for a `blade`-kind widget (fetched HTML). */
  endpoint?: string
  /** Blade render mode: `island` (default, injected HTML) or `iframe`. */
  mode?: 'island' | 'iframe'
  /** Human label shown in the Add-widget menu + frame header. */
  title: string
  /**
   * Menu/header icon. Either a lucide icon name (kebab- or PascalCase, e.g.
   * `cloud-sun`) or raw inline SVG path markup (legacy, must start with `<`).
   * Rendered via PvIcon, which handles both forms.
   */
  icon: string
  /** Default zone when first added. */
  defaultZone: WidgetZone
  /** Column span in the grid zone (1 or 2). Sidebar ignores this. */
  span?: 1 | 2
  /** Whether the pilot may remove it (default true). */
  removable?: boolean
  /** Shown in the default layout on first load. */
  defaultOn?: boolean
}

const CATALOG: WidgetDef[] = [
  { id: 'route',      component: 'RouteWidget',      title: 'Nav display',   icon: 'radar', defaultZone: 'grid', span: 2, defaultOn: true },
  { id: 'kpi-hours',  component: 'HoursWidget',      title: 'Total hours',   icon: 'clock', defaultZone: 'grid', span: 1, defaultOn: true },
  { id: 'kpi-flights',component: 'FlightsKpiWidget', title: 'Flights',       icon: 'plane', defaultZone: 'grid', span: 1, defaultOn: true },
  { id: 'kpi-balance',component: 'BalanceWidget',    title: 'Balance',       icon: 'wallet', defaultZone: 'grid', span: 1, defaultOn: true },
  { id: 'rank',       component: 'RankWidget',       title: 'Rank progress', icon: 'trending-up', defaultZone: 'grid', span: 1, defaultOn: true },
  { id: 'last-flight',component: 'LastFlightWidget', title: 'Last flight',   icon: 'plane-landing', defaultZone: 'sidebar', defaultOn: true },
  { id: 'weather',    component: 'WxWidget',         title: 'Weather (METAR)', icon: 'cloud-sun', defaultZone: 'sidebar', defaultOn: true },
]

/**
 * Server-provided widgets, merged in from `page.props.skylight.widgets` at app
 * init. Kept separate from the first-party CATALOG so getCatalog() can control
 * ordering (first-party first) and dedupe (server wins on id collision).
 */
let serverWidgets: WidgetDef[] = []

/** Register an additional widget (addon extension point). Idempotent by id. */
export function registerWidget(def: WidgetDef): void {
  if (!CATALOG.some((w) => w.id === def.id)) CATALOG.push(def)
}

/**
 * Merge server-provided widget defs into the catalog. Called once at app init
 * (PvApp setup) before anything reads the catalog. Guards undefined/empty and
 * dedupes the incoming list by id (last-write wins within the batch).
 */
export function mergeServerWidgets(defs: WidgetDef[] | undefined | null): void {
  if (!defs || !defs.length) {
    serverWidgets = []
    return
  }
  const byId = new Map<string, WidgetDef>()
  for (const def of defs) {
    if (def && typeof def.id === 'string') byId.set(def.id, def)
  }
  serverWidgets = [...byId.values()]
}

/**
 * The full widget catalog: first-party CATALOG followed by server widgets,
 * deduped by id (a server entry wins if it shares an id with a first-party one).
 */
export function getCatalog(): WidgetDef[] {
  if (!serverWidgets.length) return CATALOG
  const serverIds = new Set(serverWidgets.map((w) => w.id))
  const base = CATALOG.filter((w) => !serverIds.has(w.id))
  return [...base, ...serverWidgets]
}

/** Look up a def by id in the merged catalog. */
export function widgetById(id: string): WidgetDef | undefined {
  return getCatalog().find((w) => w.id === id)
}
