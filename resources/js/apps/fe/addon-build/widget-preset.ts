import vue from "@vitejs/plugin-vue";
import cssInjectedByJs from "vite-plugin-css-injected-by-js";
import { resolve } from "node:path";
import type { UserConfig } from "vite";

/**
 * Reusable Vite preset for building phpVMS addon Vue widgets/slot components.
 *
 * ── What this produces ────────────────────────────────────────────────────────
 * A pre-built ESM module (Vue library mode) with `vue` EXTERNALIZED, so the
 * widget shares the HOST app's single Vue instance via the import-map the SPA
 * shell emits (see resources/views/layouts/<theme>/spa.blade.php). Output lands
 * in `<addonDir>/public/widgets/` with stable, un-hashed filenames — an addon's
 * `public/` is symlinked to `public/ext/<lower-name>/`, so the widget is then
 * web-served at `/ext/<addon>/widgets/<name>.js`.
 *
 * ── Convention for in-repo addon widgets ──────────────────────────────────────
 * A first-party addon (source shipped in this repo) exposes its widget build at:
 *
 *     modules/<Name>/ui/vite.config.ts   (or .mjs / .js)
 *
 * …that imports this preset and points `addonDir` at the MODULE ROOT, e.g.:
 *
 *     // modules/Acme/ui/vite.config.ts
 *     import { resolve } from 'node:path'
 *     import { fileURLToPath } from 'node:url'
 *     import { defineAddonWidgetConfig } from
 *       '../../../resources/js/apps/fe/addon-build/widget-preset'
 *
 *     const addonDir = resolve(fileURLToPath(new URL('.', import.meta.url)), '..')
 *     export default defineAddonWidgetConfig({
 *       addonDir,
 *       entries: { 'my-widget': resolve(addonDir, 'skylight/MyWidget.ts') },
 *     })
 *
 * The core theme build discovers and runs every such config (see
 * resources/js/apps/fe/scripts/build-addons.mjs). Third-party addons (e.g. the
 * external ACARS plugin) simply copy this pattern and build in their own repo.
 *
 * ── Standalone tooling ────────────────────────────────────────────────────────
 * This file imports NOTHING from the skylight app source — it depends only on
 * `vite` + `@vitejs/plugin-vue`, so addons can vendor/copy the pattern freely.
 */

export interface AddonWidgetOptions {
  /** Absolute path to the addon/module root (the dir containing `public/`). */
  addonDir: string;
  /**
   * Library entry points: `{ '<output-name>': '<absolute-entry-path>' }`.
   * Each key becomes `<addonDir>/public/widgets/<output-name>.js`.
   */
  entries: Record<string, string>;
}

/**
 * Build a Vite config for an addon widget bundle (ESM library mode, Vue
 * externalized, un-hashed output into the addon's public/widgets/).
 */
export function defineAddonWidgetConfig(opts: AddonWidgetOptions): UserConfig {
  const { addonDir, entries } = opts;
  const outDir = resolve(addonDir, "public", "widgets");

  return {
    // `vue()` compiles SFCs; `cssInjectedByJs()` inlines the widget's compiled
    // CSS into the ESM bundle and injects it at import time. Without it, Vite
    // library mode emits a SEPARATE .css sibling that the runtime `import()` of
    // the widget never loads — so a `<style scoped>` widget would ship with no
    // structural styles. Injecting keeps each widget a single, self-contained
    // ESM file (one network request, styles guaranteed present).
    plugins: [vue(), cssInjectedByJs()],

    build: {
      // No separate CSS file — cssInjectedByJs folds it into the JS bundle.
      cssCodeSplit: false,
      outDir,
      // Do NOT nuke sibling addon public assets (css, images, other widgets).
      emptyOutDir: false,
      lib: {
        entry: entries,
        formats: ["es"],
      },
      rollupOptions: {
        // `vue` is externalized so the widget resolves the bare "vue" specifier
        // via the shell's import-map to the single shared vendor Vue — one ESM
        // instance across host + all addons.
        //
        // NOTE: when the shared design-system package ships, add '@skylight/ui'
        // here (and to the shell import-map) so widgets share it too:
        //   external: ['vue', '@skylight/ui'],
        external: ["vue"],
        output: {
          // Stable, un-hashed URLs so blade/import references stay constant.
          entryFileNames: "[name].js",
          chunkFileNames: "[name].js",
          assetFileNames: "[name][extname]",
        },
      },
    },
  };
}
