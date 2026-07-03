import type { ComponentResolver } from '@/lib/registry'
import WeatherWidget from './WeatherWidget.vue'

/**
 * Concrete component resolver: maps registry `component` NAMES to Vue
 * components. Kept separate from the headless registry so the registry stays
 * serializable. Provided at the app root by PvApp.
 */
export const widgetComponents: ComponentResolver = {
  WeatherWidget,
}
