import { defineConfig } from "vite";
import laravel, { refreshPaths } from "laravel-vite-plugin";
import tailwindcss from '@tailwindcss/vite'
// import react from '@vitejs/plugin-react';
// import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    tailwindcss(),
    laravel({
      input: [
        "resources/sass/app.scss",
        // "resources/sass/admin/paper-dashboard.scss",
        "resources/sass/frontend/styles.scss",
        "resources/sass/now-ui/now-ui-kit.scss",
        "resources/sass/fonts/glyphicons-halflings-regular.woff2",
        "resources/sass/fonts/Pe-icon-7-stroke.eot",
        "resources/sass/fonts/Pe-icon-7-stroke.svg",
        "resources/sass/fonts/Pe-icon-7-stroke.ttf",
        "resources/sass/fonts/Pe-icon-7-stroke.woff",
        "resources/css/filament/admin/theme.css",
        // "resources/js/admin/app.js",
        // "resources/js/admin/airport_lookup.js",
        // "resources/js/admin/calculate_distance.js",
        "resources/js/common.js",
        "resources/js/config.js",
        "resources/js/entrypoint.js",
        "resources/js/request.js",
        "resources/js/storage.js",
        "resources/js/frontend/app.js",
        "resources/js/frontend/bids.js",
        "resources/js/installer/app.js",
        "public/assets/global/js/jquery.js",
        "public/assets/global/js/simbrief.apiv1.js",
      ],
      refresh: [...refreshPaths, "app/Filament/**", "modules/**/**"],
    }),
  ],
  // server: {
  //   hmr: {
  //     host: "localhost",
  //   },
  //   watch: {
  //     usePolling: true,
  //   },
  // },
});
