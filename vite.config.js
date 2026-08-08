import { defineConfig } from "vite";
import laravel, { refreshPaths } from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import preact from "@preact/preset-vite";
// import react from '@vitejs/plugin-react';
// import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    tailwindcss(),
    // reactAliasesEnabled: false — rqb-core (resources/js/rqb/) bundles a
    // genuine React runtime (react-querybuilder needs it), not Preact. The
    // default preact() config aliases the bare `react`/`react-dom` specifiers
    // to preact/compat project-wide, which would silently swap out the real
    // React we `bun add`ed. Safe to disable: nothing else in resources/js
    // imports "react"/"react-dom" directly (they import "preact" instead).
    preact({ reactAliasesEnabled: false }),
    laravel({
      input: [
        "resources/css/filament/admin/theme.css",
        "resources/js/admin/app.js",
        "resources/js/admin/routeforge/main.tsx",
        "resources/js/rqb/main.tsx",
        "resources/js/entrypoint.js",
        "resources/js/frontend/app.js",
        // "public/assets/global/js/jquery.js",
        // "public/assets/global/js/simbrief.apiv1.js",
      ],
      refresh: [...refreshPaths, "app/Filament/**", "modules/**/**"],
    }),
  ],
  server: {
    watch: {
      // Never watch dependency or runtime-churn dirs. Critically, a linked
      // module (e.g. phpvms-vacentral) carries a `vendor/phpvms/phpvms`
      // symlink back to the project root, so following it would make the
      // root's storage/ writes (logs, sessions, debugbar — written on every
      // request) look like `modules/**` changes and trigger an endless
      // full-reload loop that makes the installer page unusable.
      ignored: ["**/vendor/**", "**/storage/**"],
    },
  },
});
