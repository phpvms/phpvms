import { defineConfig, type Plugin } from "vitest/config";
import vue from "@vitejs/plugin-vue";
import icons from "unplugin-icons/vite";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));

const NUXT_UI_COMPONENT_RE =
  /^@nuxt\/ui\/(?:components|runtime\/vue\/components|runtime\/vue\/overrides\/\w+)\/(\w+)\.vue$/;
const STUB_PREFIX = "\0nuxt-ui-stub:";

/**
 * Stand in for Nuxt UI's components under test.
 *
 * Their real sources import `#imports` and `#build/*`, virtual modules only the
 * Nuxt UI Vite plugin serves, and that plugin is deliberately not in this config
 * — tests exercise phpVMS behaviour, not Nuxt UI's. Each import resolves to a
 * passthrough that renders its own tag around the default slot, which is what
 * an unresolved `<UButton>` did before the components were imported by name.
 * Tests that need real behaviour pass their own `global.stubs` entry, matched
 * on the `name` below.
 */
function nuxtUiComponentStubs(): Plugin {
  return {
    name: "nuxt-ui-component-stubs",
    enforce: "pre",
    resolveId(id) {
      const match = NUXT_UI_COMPONENT_RE.exec(id);
      return match ? `${STUB_PREFIX}U${match[1]}` : undefined;
    },
    load(id) {
      if (!id.startsWith(STUB_PREFIX)) {
        return;
      }
      const name = JSON.stringify(id.slice(STUB_PREFIX.length));
      return `import { h } from "vue";
export default {
  name: ${name},
  inheritAttrs: false,
  setup: (_props, { attrs, slots }) => () => h(${name}, attrs, slots.default?.()),
};`;
    },
  };
}

/**
 * Root vitest config — runs all test files under tests/src/ in jsdom
 * with @vue/test-utils. `@` aliases the SPA source tree.
 *
 * `icons` mirrors the app build so components importing `~icons/tabler/*`
 * resolve here too.
 */
export default defineConfig({
  plugins: [nuxtUiComponentStubs(), vue(), icons({ compiler: "vue3", autoInstall: false })],
  test: {
    environment: "jsdom",
    globals: true,
    include: ["tests/src/**/*.test.ts"],
  },
  resolve: {
    alias: {
      "@": resolve(__dirname, "src"),
    },
  },
});
