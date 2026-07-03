import type { Component } from 'vue'
import RouteWidget from './RouteWidget.vue'
import HoursWidget from './HoursWidget.vue'
import FlightsKpiWidget from './FlightsKpiWidget.vue'
import BalanceWidget from './BalanceWidget.vue'
import RankWidget from './RankWidget.vue'
import LastFlightWidget from './LastFlightWidget.vue'
import WxWidget from './WxWidget.vue'

/**
 * Resolver map: catalog `component` NAME → Vue component. Kept out of the
 * headless catalog so the catalog stays serializable. Addons that register a
 * WidgetDef also add their component here (or via a future dynamic import).
 */
export const dashboardWidgets: Record<string, Component> = {
  RouteWidget,
  HoursWidget,
  FlightsKpiWidget,
  BalanceWidget,
  RankWidget,
  LastFlightWidget,
  WxWidget,
}
