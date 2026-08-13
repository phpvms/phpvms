/// <reference types="vite/client" />
import { createApp, type DefineComponent } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { i18nVue } from "laravel-vue-i18n";
import ui from "@nuxt/ui/vue-plugin";
import {
  initialThemeDocument,
  resolveTheme,
  ThemeContextKey,
  type ThemeInertiaProps,
} from "@/shared/theme";
import PvApp from "./PvApp.vue";

/**
 * Theme entry point. Bootstraps the Inertia + Vue app.
 *
 * Pages resolve from src/pages/<Name>.vue — dropping a .vue file registers
 * a page, no registry edits. The app chrome (nav rail + header) lives in PvApp,
 * set here as the default Inertia layout so pages don't import the app layer
 * (which would be a layer-inversion under FSD).
 */
const pages = import.meta.glob<{ default: DefineComponent }>("../pages/**/*.vue");

createInertiaApp<ThemeInertiaProps & Record<string, unknown>>({
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
    // Fill the default ONLY when the page declared none; a page opts OUT of the
    // app chrome (renders bare) with an explicit `layout: null` — so guard on
    // `undefined`, not nullish (`??=` would overwrite an intentional null).
    const c = component as Record<string, unknown>;
    if (c.layout === undefined) c.layout = PvApp;
    return component;
  },
  setup({ el, App, props, plugin }) {
    // laravel-vue-i18n, fed from the Inertia-shared `i18n` prop (the server reads
    // the Laravel lang files). We ship only the active locale's flat message map,
    // so `resolve` returns it for that lang and {} otherwise. Locale changes go
    // through the server cookie flow + a full reload, so a boot snapshot is fine.
    const shared = props.initialPage.props;
    const i18n = (shared.i18n as
      | { locale: string; messages: Record<string, string> }
      | undefined) ?? {
      locale: "en",
      messages: {},
    };
    const document = initialThemeDocument(shared.theme);
    const theme = { document, resolved: resolveTheme(document) };

    createApp(App, props as unknown as Record<string, unknown>)
      .use(plugin)
      .use(ui)
      .provide(ThemeContextKey, theme)
      .use(i18nVue, {
        lang: i18n.locale,
        fallbackLang: "en",
        resolve: (lang: string) => (lang === i18n.locale ? i18n.messages : {}),
      })
      .mount(el);
  },
});
