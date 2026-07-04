import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// Import the SHARED addon widget/slot build preset by relative path. The preset
// imports nothing skylight-internal (only vite + @vitejs/plugin-vue), so a real
// third-party addon (e.g. the external ACARS plugin) would vendor/copy this same
// pattern into its own repo.
import { defineAddonWidgetConfig } from '../../../resources/skylight/addon-build/widget-preset'

// Module root = the dir that contains `public/`. This file lives in
// modules/SampleVueSlot/ui/, so the module root is one level up.
const addonDir = resolve(fileURLToPath(new URL('.', import.meta.url)), '..')

/**
 * DISCOVERY CONVENTION:
 * The core theme build (`pnpm build` in resources/skylight, via
 * scripts/build-addons.mjs) globs for modules/<Name>/ui/vite.config.ts and
 * runs `vite build` on each. `pnpm build:addons` runs only that step.
 *
 * OUTPUT: `entries` keys become filenames under <addonDir>/public/widgets/.
 * The 'slot' key → modules/SampleVueSlot/public/widgets/slot.js, which is exactly
 * the path the ServiceProvider registers as the slot entry `module` URL (served
 * at /ext/samplevueslot/widgets/slot.js once public/ is relinked).
 *
 * The preset externalizes `vue` (bare `import ... from "vue"` in the output) and
 * emits stable, un-hashed ESM filenames so the URL stays constant.
 */
export default defineAddonWidgetConfig({
  addonDir,
  entries: {
    slot: resolve(addonDir, 'ui/SampleBidsSlot.vue'),
  },
})
