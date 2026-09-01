/**
 * Measures the compiled size of resources/css/tailwind-safelist.css in
 * isolation (`@import "tailwindcss" source(none);`), so the number reflects
 * only the safelist — not file-scanning from the rest of the app, and not
 * whatever a real production bundle happens to dedupe against.
 *
 * The proposal's measurement method used `@tailwindcss/postcss`. That
 * package is not installed here (neither directly nor transitively) — only
 * `@tailwindcss/vite` is, as a real devDependency the app already builds
 * with. Rather than add a dependency to take an unused measurement path
 * (openspec/changes/tailwind-utility-safelist/proposal.md's stated
 * alternative), this compiles through the same `@tailwindcss/vite` plugin
 * and `vite` the real builds use — see design.md's "Measurement method"
 * note, which names both options as valid.
 *
 * Usage:
 *   node bin/measure-tailwind-safelist.mjs
 *
 * Exits 1 and prints the offending size if the safelist's brotli size
 * exceeds BUDGET_BROTLI_BYTES. Also used as a library by
 * bin/measure-tailwind-safelist.test.mjs.
 */
import { build } from "vite";
import tailwindcss from "@tailwindcss/vite";
import { writeFileSync, readFileSync, readdirSync, rmSync, mkdtempSync } from "node:fs";
import { gzipSync, brotliCompressSync } from "node:zlib";
import { fileURLToPath } from "node:url";
import { dirname, resolve as pathResolve, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = pathResolve(__dirname, "..");
const SAFELIST_PATH = pathResolve(REPO_ROOT, "resources/css/tailwind-safelist.css");

/**
 * Brotli budget for the safelist, compiled in isolation. Recorded from a
 * real compile of the D3 covered surface in this repo (740_567 raw / 88_269
 * gzip / 30_797 brotli, tailwindcss 4.2.2) — see design.md D5. That figure is
 * lower than the proposal's 937K/91K/31K because this enumeration and the
 * proposal's are not byte-identical; both are real compiles of the same D3
 * surface, not estimates. A change that pushes the isolated compile above
 * this budget is a real regression, not noise: adding one more family moves
 * brotli by thousands of bytes, not tens.
 */
export const BUDGET_BROTLI_BYTES = 32 * 1024; // 32 KiB — ~6% headroom over the measured 30_797 B

/**
 * Compile Tailwind entry CSS through `@tailwindcss/vite` and return the
 * minified output's raw/gzip/brotli byte sizes.
 *
 * @param {string} css - Tailwind entry CSS, e.g.
 *   `@import "tailwindcss" source(none); @import "...";`
 * @param {{ extraFiles?: Record<string, string> }} [options] - Extra files to
 *   drop next to the entry (unreferenced by any `@source`), used to prove
 *   `source(none)` isolation from unrelated content on disk.
 * @returns {Promise<{ raw: number, gzip: number, brotli: number, css: string }>}
 */
export async function measureCompiledCss(css, { extraFiles = {} } = {}) {
  const tmp = mkdtempSync(join(REPO_ROOT, ".tailwind-safelist-measure-"));
  try {
    for (const [name, content] of Object.entries(extraFiles)) {
      writeFileSync(join(tmp, name), content);
    }
    const entry = join(tmp, "entry.css");
    writeFileSync(entry, css);

    const outDir = join(tmp, "out");
    await build({
      root: REPO_ROOT,
      logLevel: "silent",
      configFile: false,
      plugins: [tailwindcss()],
      build: {
        outDir,
        write: true,
        cssMinify: true,
        rollupOptions: { input: entry },
      },
    });

    const assetsDir = join(outDir, "assets");
    const cssFile = readdirSync(assetsDir).find((file) => file.endsWith(".css"));
    if (!cssFile) {
      throw new Error("Tailwind compile produced no CSS asset");
    }
    const compiled = readFileSync(join(assetsDir, cssFile), "utf8");

    return {
      raw: compiled.length,
      gzip: gzipSync(compiled).length,
      brotli: brotliCompressSync(compiled).length,
      css: compiled,
    };
  } finally {
    rmSync(tmp, { recursive: true, force: true });
  }
}

/** Compile the real safelist partial in isolation (`source(none)`). */
export async function measureSafelist(options = {}) {
  const css = `@import "tailwindcss" source(none);\n@import "${SAFELIST_PATH}";\n`;
  return measureCompiledCss(css, options);
}

async function main() {
  const start = Date.now();
  const { raw, gzip, brotli } = await measureSafelist();
  const elapsedMs = Date.now() - start;

  console.log(
    `tailwind-safelist (isolated, source(none)): raw ${raw} B, gzip ${gzip} B, brotli ${brotli} B (${elapsedMs}ms)`,
  );

  if (brotli > BUDGET_BROTLI_BYTES) {
    console.error(
      `FAIL: safelist brotli size ${brotli} B exceeds the recorded budget of ${BUDGET_BROTLI_BYTES} B. ` +
        "If this growth is intentional, update BUDGET_BROTLI_BYTES in this file and record why in " +
        "openspec/changes/tailwind-utility-safelist/design.md.",
    );
    process.exitCode = 1;
    return;
  }

  console.log(`OK: within the ${BUDGET_BROTLI_BYTES} B budget.`);
}

if (import.meta.url === `file://${process.argv[1]}`) {
  main();
}
