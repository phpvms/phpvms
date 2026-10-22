import { gzipSync } from "node:zlib";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { build } from "vite";
import { describe, expect, it } from "vitest";

/**
 * Vite 8 builds on rolldown internally and exports its output types from
 * `"rolldown"`, not `"vite"` — a version-specific detail this test should
 * not be coupled to. Typed structurally against the one shape actually
 * used below instead.
 */
type BuiltChunk = { type: "chunk"; isEntry: boolean; code: string; dynamicImports: string[] };
type BuildOutput = { output: (BuiltChunk | { type: "asset" })[] };

/**
 * Build-time assertion for design.md D13's tiering (tasks.md 2b.3): a real
 * production build of `index.ts`, checked so the split cannot silently
 * regress — Tier 0 (`tier0-line.ts`) must ship with NO three.js in its
 * module graph, and Tier 1 (`route-line.ts`) must only be reachable through
 * the dynamic `import()` in `createRoute()`.
 *
 * A real `vite build()` rather than a source-text grep: the whole point is
 * to catch an accidental STATIC import creeping in somewhere that would pull
 * three.js into the eager chunk — grepping `index.ts` alone would miss that
 * entirely, since the regression would show up in what the BUNDLER decides
 * to inline, not in the source file itself.
 */

const PACKAGE_ROOT = dirname(fileURLToPath(import.meta.url)); // .../src, but entry paths are relative to it via "."

/**
 * Every public entry point a REAL consumer imports from — not just the core `index.ts`.
 * Confirmed a real gap (codex review, verified): this file used to build ONLY `index.ts`, so a
 * static three.js import accidentally added to either adapter (`adapters/imperative.ts`,
 * `adapters/vue/index.ts`) would reach admin/fe-vue's real bundles while this test stayed green
 * — the Vue adapter is the likeliest place for that to happen (it is the newest, and pulls in
 * `vue` as a peer dependency, which is externalized below the same way `maplibre-gl` is).
 */
const ENTRIES = {
  core: resolve(PACKAGE_ROOT, "index.ts"),
  imperative: resolve(PACKAGE_ROOT, "adapters/imperative.ts"),
  vue: resolve(PACKAGE_ROOT, "adapters/vue/index.ts"),
} as const;

/**
 * `minify` is a parameter, not a fixed setting, because this file needs BOTH
 * builds for different reasons and conflating them is a real trap: the
 * unminified build keeps class names like `WebGLRenderer` readable for the
 * source-text assertions below, but its gzipped size is NOT what a browser
 * ever downloads — a number logged from it reads exactly like a shipping
 * size and isn't one. `sizeReport()` below builds minified, on purpose, so
 * the number it reports is the real one.
 */
async function buildPackage(entry: string, minify: boolean): Promise<BuildOutput> {
  const result = await build({
    root: resolve(PACKAGE_ROOT, ".."),
    logLevel: "silent",
    build: {
      write: false,
      minify,
      lib: {
        entry,
        formats: ["es"],
        fileName: () => "index.js",
      },
      rollupOptions: {
        // maplibre-gl is only ever type-imported by this package (the caller constructs and owns
        // the Map instance) — externalizing it here just documents that; it changes nothing about
        // which chunk three.js ends up in, which is what this test actually checks. `vue` is a
        // real peer dependency of the Vue adapter entry specifically (unused, and absent from
        // `rollupOptions.external`'s effect, for the other two entries).
        // `maplibre-gl/dist/maplibre-gl-worker.mjs?url` is externalized for the same reason as
        // maplibre-gl itself: the CONSUMING app resolves and emits it, the package does not ship
        // it. Left bundled, this synthetic `write: false` build pulls the worker module in as
        // plain JS and reports ~14 kB gzipped for a Tier 0 that really ships ~4 kB — the real
        // build emits `maplibre-gl-worker-*.mjs` as its own 19 kB asset and the chunk keeps only
        // the URL string (measured across that change: 3.57 -> 3.61 kB gzipped).
        external: [/^maplibre-gl(\/|$)/, "vue"],
      },
    },
  });
  // `build()` returns an array — one output per output format, even when only one was asked for
  // (`formats: ["es"]`, so this is always index 0 here).
  return (result as unknown as BuildOutput[])[0];
}

function chunks(output: BuildOutput): BuiltChunk[] {
  return output.output.filter((item): item is BuiltChunk => item.type === "chunk");
}

describe.each(Object.entries(ENTRIES))(
  "Tier 0 / Tier 1 bundle split (design.md D13) — %s entry",
  (_name, entry) => {
    // These three assertions build UNMINIFIED on purpose — the whole point of a real `vite build()`
    // instead of a source grep on `index.ts` is to catch an accidental static import that the
    // BUNDLER decides to inline, and that needs readable class names (`WebGLRenderer`, ...) to
    // check for. None of these log a byte count — see `sizeReport()` below for that, built
    // minified, which is the number that actually matters for what ships.
    //
    // Run against EVERY public entry point (`ENTRIES`, not just `index.ts`) — confirmed a real gap
    // (codex review, verified): the split only actually matters at the boundary a real consumer
    // (admin, fe-vue) imports from, and a static three.js import could sneak into either adapter
    // without `index.ts`'s own build ever seeing it.

    it("produces at least two chunks: the eager entry and a dynamically-imported Tier 1 chunk", async () => {
      const output = await buildPackage(entry, false);
      const eagerChunk = chunks(output).find((c) => c.isEntry);
      expect(eagerChunk).toBeDefined();
      // A dynamic import() in a chunk's own code, not `output.length > 1` alone — that would also
      // pass if two chunks existed for an unrelated reason (e.g. a shared vendor chunk).
      expect(eagerChunk!.dynamicImports.length).toBeGreaterThan(0);
    }, 30_000);

    it("the eager entry chunk contains no three.js — Tier 0 pays nothing for the 3D engine", async () => {
      const output = await buildPackage(entry, false);
      const eagerChunk = chunks(output).find((c) => c.isEntry)!;

      // three.js's own class/API names, not a generic "three" substring match (which would also
      // false-positive on ordinary English words like "three colours" in a doc comment — this
      // file's own `createRoute()` doc comment tripped exactly that once, see `index.ts`).
      const threeJsSignatures = [
        "WebGLRenderer",
        "BufferGeometry",
        "class Vector3",
        "class Matrix4",
        "LineMaterial",
      ];
      for (const signature of threeJsSignatures) {
        expect(eagerChunk.code).not.toContain(signature);
      }
    }, 30_000);

    it("the dynamically-imported chunk(s) DO contain three.js — the split isn't just an empty stub", async () => {
      const output = await buildPackage(entry, false);
      const lazyChunks = chunks(output).filter((c) => !c.isEntry);
      expect(lazyChunks.length).toBeGreaterThan(0);
      const combined = lazyChunks.map((c) => c.code).join("\n");
      expect(combined).toContain("WebGLRenderer");
    }, 30_000);
  },
);

describe("Tier 0 / Tier 1 bundle size (design.md D13)", () => {
  it("records both chunks' MINIFIED gzipped size — the shipping number — for the tasks.md 2b.3 report", async () => {
    // A SEPARATE build from the assertions above, minified, because gzipping unminified source
    // (readable on purpose, for the grep assertions) produces a number that LOOKS like a shipping
    // size and isn't — measured ~253 KB for Tier 1 unminified vs. ~146 KB minified, a gap large
    // enough to mislead a design doc. This build exists only to report the real one. Reports the
    // CORE (`index.ts`) entry only — the canonical number tasks.md 2b.3 documents; the adapter
    // entries' own weight (maplibre-gl, vue) is a different, separately-understood cost.
    const output = await buildPackage(ENTRIES.core, true);
    const entry = chunks(output).find((c) => c.isEntry)!;
    const lazy = chunks(output).filter((c) => !c.isEntry);

    const entryGzip = gzipSync(Buffer.from(entry.code)).length;
    const lazyGzip = lazy.reduce((sum, c) => sum + gzipSync(Buffer.from(c.code)).length, 0);

    console.log(`[tiering] Tier 0 (eager) chunk, MINIFIED: ${entryGzip} bytes gzipped`);
    console.log(
      `[tiering] Tier 1 (lazy) chunk(s), MINIFIED: ${lazyGzip} bytes gzipped, across ${lazy.length} chunk(s)`,
    );

    // Sanity bounds, not exact numbers — the exact byte count drifts with every three.js patch
    // release; what must hold is the SHAPE of the split (Tier 0 small, Tier 1 an order of
    // magnitude bigger because it carries three.js).
    expect(entryGzip).toBeLessThan(5_000);
    expect(lazyGzip).toBeGreaterThan(entryGzip * 10);
  }, 30_000);
});
