/// <reference types="vite/client" />
import { createApp, type DefineComponent } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { i18nVue } from "laravel-vue-i18n";
import PvApp from "./PvApp.vue";

// Tailwind v4 + canonical --pv-* tokens + shadcn bridge (imports lib/tokens.css).
import "./app.css";
// MapLibre GL base styles for the Nav Display globe.
import "maplibre-gl/dist/maplibre-gl.css";

/**
 * Theme entry point. Bootstraps the Inertia + Vue app.
 *
 * Pages resolve from src/pages/<Name>.vue — dropping a .vue file registers
 * a page, no registry edits. The app chrome (nav rail + header) lives in PvApp,
 * set here as the default Inertia layout so pages don't import the app layer
 * (which would be a layer-inversion under FSD).
 */
const pages = import.meta.glob<{ default: DefineComponent }>("../pages/**/*.vue");

createInertiaApp({
  resolve: async (name: string) => {
    const importer = pages[`../pages/${name}.vue`];
    if (!importer) {
      throw new Error(
        `[skylight] Inertia page not found: ${name}. ` +
          `Add src/pages/${name}.vue to register it.`,
      );
    }
    const component = (await importer()).default;
    // App layer owns the default layout — pages must not import PvApp directly.
    (component as Record<string, unknown>).layout ??= PvApp;
    return component;
  },
  setup({ el, App, props, plugin }) {
    // laravel-vue-i18n, fed from the Inertia-shared `i18n` prop (the server reads
    // the Laravel lang files). We ship only the active locale's flat message map,
    // so `resolve` returns it for that lang and {} otherwise. Locale changes go
    // through the server cookie flow + a full reload, so a boot snapshot is fine.
    const shared = (props as { initialPage: { props: Record<string, unknown> } }).initialPage.props;
    const i18n = (shared.i18n as
      | { locale: string; messages: Record<string, string> }
      | undefined) ?? {
      locale: "en",
      messages: {},
    };

    createApp(App, props as unknown as Record<string, unknown>)
      .use(plugin)
      .use(i18nVue, {
        lang: i18n.locale,
        fallbackLang: "en",
        resolve: (lang: string) => (lang === i18n.locale ? i18n.messages : {}),
      })
      .mount(el);
  },
});
