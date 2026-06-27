import { defineConfig } from "vite";
import laravel, { refreshPaths } from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import preact from "@preact/preset-vite";
// import react from '@vitejs/plugin-react';
// import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    tailwindcss(),
    preact(),
    laravel({
      input: [
        "resources/css/filament/admin/theme.css",
        "resources/js/admin/app.js",
        "resources/js/admin/routeforge/main.tsx",
        // "resources/js/frontend/app.js",
        // "public/assets/global/js/jquery.js",
        // "public/assets/global/js/simbrief.apiv1.js",
      ],
      refresh: [...refreshPaths, "app/Filament/**", "modules/**/**"],
    }),
  ],
  server: {
    watch: {
      // Don't follow symlinks into addon modules — they can contain vendor
      // directories that reference back to this app's own storage/ directory,
      // causing every request (session/debugbar writes) to trigger a full reload.
      followSymlinks: false,
      // Belt-and-suspenders: also exclude known heavy directories under modules.
      ignored: ["**/modules/**/vendor/**", "**/modules/**/node_modules/**", "**/storage/**"],
    },
  },
});
