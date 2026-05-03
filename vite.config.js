import { defineConfig } from "vite";
import laravel, { refreshPaths } from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
// import react from '@vitejs/plugin-react';
// import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    tailwindcss(),
    laravel({
      input: [
        "resources/css/filament/admin/theme.css",
        "resources/js/entrypoint.js",
        "resources/js/frontend/app.js",
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
