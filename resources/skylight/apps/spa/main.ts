/// <reference types="vite/client" />
import { createApp, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

// Tailwind v4 + canonical --pv-* tokens + shadcn bridge (imports lib/tokens.css).
import './app.css'
// MapLibre GL base styles for the Nav Display globe.
import 'maplibre-gl/dist/maplibre-gl.css'

/**
 * Theme entry point. Bootstraps the Inertia + Vue app.
 *
 * Pages resolve from apps/spa/pages/<Name>.vue — dropping a .vue file registers
 * a page, no registry edits. The app chrome (nav rail + header) lives in PvApp,
 * used as each page's persistent Inertia layout.
 */
const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue')

createInertiaApp({
  resolve: async (name: string) => {
    const importer = pages[`./pages/${name}.vue`]
    if (!importer) {
      throw new Error(
        `[skylight] Inertia page not found: ${name}. ` +
          `Add apps/spa/pages/${name}.vue to register it.`,
      )
    }
    return (await importer()).default
  },
  setup({ el, App, props, plugin }) {
    createApp(App, props as unknown as Record<string, unknown>).use(plugin).mount(el)
  },
})
