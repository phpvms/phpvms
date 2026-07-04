import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// Import the SHARED addon widget preset by relative path. The preset imports
// nothing skylight-internal (only vite + @vitejs/plugin-vue), so a real
// third-party addon would vendor/copy this pattern into its own repo.
import { defineAddonWidgetConfig } from '../../../resources/skylight/addon-build/widget-preset'

// Module root = the dir that contains `public/`. This file lives in
// modules/SampleVueWidget/ui/, so the module root is one level up.
const addonDir = resolve(fileURLToPath(new URL('.', import.meta.url)), '..')

/**
 * DISCOVERY CONVENTION:
 * The core theme build (`pnpm build` in resources/skylight, via
 * scripts/build-addons.mjs) globs for modules/<Name>/ui/vite.config.ts
 * and runs `vite build` on each. `pnpm build:addons` runs only that step.
 *
 * OUTPUT: `entries` keys become filenames under <addonDir>/public/widgets/.
 * The 'sample' key → modules/SampleVueWidget/public/widgets/sample.js, which is
 * exactly the path the ServiceProvider registers as the widget `module` URL
 * (served at /ext/samplevuewidget/widgets/sample.js once public/ is relinked).
 *
 * The preset externalizes `vue` and emits stable, un-hashed ESM filenames.
 */
export default defineAddonWidgetConfig({
  addonDir,
  entries: {
    sample: resolve(addonDir, 'ui/SampleVueWidget.vue'),
  },
})
