import { describe, it, expect } from "vitest";
import { build, type RollupOutput } from "vite";
import vue from "@vitejs/plugin-vue";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
// tests/src/elements -> fe-vue root
const WORKSPACE_ROOT = resolve(__dirname, "..", "..", "..");
const ELEMENTS_ENTRY = resolve(WORKSPACE_ROOT, "src", "elements", "index.ts");

/**
 * The whole CSP argument in this change's proposal ("no `unsafe-eval` needed")
 * rests on the elements bundle never loading Vue's runtime template compiler
 * (`registerRuntimeCompiler`, which is where `new Function` lives — full Vue
 * build only). This builds the REAL entry with the REAL SFC compiler plugin
 * and inspects the emitted code, rather than trusting that bare `import "vue"`
 * resolves to the runtime-only build (task 1.2, 6.3).
 *
 * Scoped to `@vitejs/plugin-vue` only — none of the current first-party
 * elements (task 3.1) import Nuxt UI or icon components. An element that adds
 * either would need `@nuxt/ui/vite` / `unplugin-icons` added here too.
 */
describe("custom-elements bundle: no runtime template compiler", () => {
  it("the built output contains no `new Function`", async () => {
    const result = (await build({
      root: WORKSPACE_ROOT,
      logLevel: "silent",
      configFile: false,
      plugins: [vue()],
      build: {
        write: false,
        rollupOptions: { input: ELEMENTS_ENTRY },
      },
    })) as RollupOutput | RollupOutput[];

    const outputs = Array.isArray(result) ? result : [result];
    const chunks = outputs.flatMap((r) =>
      r.output.filter(
        (o): o is Extract<(typeof r.output)[number], { code: string }> => "code" in o,
      ),
    );

    expect(chunks.length).toBeGreaterThan(0);
    for (const chunk of chunks) {
      expect(chunk.code).not.toContain("new Function");
    }
  }, 30_000);
});
