/**
 * End-to-end checks that the safelist actually fixes the defect described in
 * openspec/changes/tailwind-utility-safelist/proposal.md: a bare Tailwind
 * utility resolves even when no file `@source` scans contains it (the
 * `modules/` and database-content cases), a safelisted utility beats a
 * component default the way D4 says it must, and both real Tailwind builds
 * in the repo share the same covered surface.
 *
 * These compile the REAL `resources/css/filament/admin/theme.css` — the
 * actual `@source` roots, the actual vendor Filament import, the actual
 * safelist import wired in by this change — through the same
 * `@tailwindcss/vite` plugin the app builds with. Not mocked.
 *
 * The SPA side (`resources/js/apps/fe-vue/src/app/app.css`) is NOT compiled
 * here: `resources/js/apps/fe-vue` is a separate pnpm workspace member with
 * its own dependencies (`@nuxt/ui`, `@vitejs/plugin-vue`, …), and those are
 * not installed in this environment — running an install was out of scope
 * for this change. The SPA-side assertion below instead verifies the D2
 * mechanism that guarantees a shared surface: both entry files resolve the
 * exact same partial file. That is what makes drift between the two builds
 * structurally impossible, rather than something that happens to be true
 * today because both were built and diffed once.
 */
import { execFileSync } from "node:child_process";
import { mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join, resolve as pathResolve } from "node:path";
import { fileURLToPath } from "node:url";
import tailwindcss from "@tailwindcss/vite";
import { build } from "vite";
import { describe, expect, it } from "vitest";

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPO_ROOT = pathResolve(__dirname, "..");
const THEME_CSS = pathResolve(REPO_ROOT, "resources/css/filament/admin/theme.css");
const APP_CSS = pathResolve(REPO_ROOT, "resources/js/apps/fe-vue/src/app/app.css");
const SAFELIST_CSS = pathResolve(REPO_ROOT, "resources/css/tailwind-safelist.css");

/** Compile a real CSS entry point through the app's own Tailwind plugin. */
async function compileEntry(entryPath) {
  const outDir = mkdtempSync(join(REPO_ROOT, ".tailwind-safelist-integration-"));
  try {
    await build({
      root: REPO_ROOT,
      logLevel: "silent",
      configFile: false,
      plugins: [tailwindcss()],
      build: {
        outDir,
        write: true,
        cssMinify: false,
        rollupOptions: { input: entryPath },
      },
    });
    const assetsDir = join(outDir, "assets");
    const cssFile = readdirSync(assetsDir).find((file) => file.endsWith(".css"));
    if (!cssFile) {
      throw new Error(`Tailwind compile of ${entryPath} produced no CSS asset`);
    }
    return readFileSync(join(assetsDir, cssFile), "utf8");
  } finally {
    rmSync(outDir, { recursive: true, force: true });
  }
}

/**
 * True if no tracked file in the repo — other than this test file, which
 * necessarily names the probe class — contains `needle` (git grep, exit 1 = no
 * match).
 */
function unusedAnywhereInRepo(needle) {
  try {
    execFileSync(
      "git",
      ["grep", "-q", "--", needle, ":(exclude)bin/tailwind-safelist-integration.test.mjs"],
      { cwd: REPO_ROOT },
    );
    return false; // grep found a match
  } catch (error) {
    if (error.status === 1) {
      return true; // no match — this is the expected "unused" case
    }
    throw error;
  }
}

/**
 * Returns the brace-matched body of the first top-level `@layer <name> { ... }`
 * block in compiled CSS output (as opposed to the layer-order statement
 * `@layer a, b, c;`, which has no body). A naive regex stops at the first
 * nested `}`, which is wrong here — a layer body contains thousands of rules.
 */
function extractLayerBody(css, layerName) {
  const marker = `@layer ${layerName} {`;
  const start = css.indexOf(marker);
  if (start === -1) {
    throw new Error(`No \`${marker}\` block found in compiled CSS`);
  }
  let depth = 0;
  for (let i = start + marker.length - 1; i < css.length; i++) {
    if (css[i] === "{") depth++;
    else if (css[i] === "}") {
      depth--;
      if (depth === 0) {
        return css.slice(start + marker.length, i);
      }
    }
  }
  throw new Error(`Unbalanced braces in \`${marker}\` block`);
}

describe("tailwind-safelist fixes unscanned-author cases (real admin build)", () => {
  it("modules/ is not one of the admin theme's @source roots", () => {
    const source = readFileSync(THEME_CSS, "utf8");
    const sourceDirectives = source.match(/^@source .*/gm) ?? [];
    expect(sourceDirectives.length).toBeGreaterThan(0);
    for (const directive of sourceDirectives) {
      expect(directive).not.toMatch(/modules\//);
    }
  });

  it("resolves a bare utility no scanned file contains — the modules/ and database-content cases (3.1, 3.2)", async () => {
    const probeClass = "bg-fuchsia-950"; // in the safelist's colour family; verified unused below
    expect(unusedAnywhereInRepo(probeClass)).toBe(true);

    const css = await compileEntry(THEME_CSS);
    expect(css).toContain(`.${probeClass}`);
  }, 30_000);

  it("a safelisted utility overrides a components-layer default with no !important (3.3)", async () => {
    // Same layer mechanism D4 relies on (`@layer theme, base, components,
    // utilities;`, utilities last) — proven directly rather than via the SPA
    // build, since @nuxt/ui itself is just one components-layer author among
    // many and isn't installed in this environment (see file header).
    const outDir = mkdtempSync(join(REPO_ROOT, ".tailwind-safelist-layer-"));
    try {
      const entry = join(outDir, "entry.css");
      writeFileSync(
        entry,
        `
          @layer theme, base, components, utilities;
          @import "tailwindcss" source(none);
          @import "${SAFELIST_CSS}";
          @layer components {
            .probe { background-color: red; }
          }
        `,
      );
      await build({
        root: REPO_ROOT,
        logLevel: "silent",
        configFile: false,
        plugins: [tailwindcss()],
        build: {
          outDir: join(outDir, "out"),
          write: true,
          cssMinify: false,
          rollupOptions: { input: entry },
        },
      });
      const assetsDir = join(outDir, "out", "assets");
      const cssFile = readdirSync(assetsDir).find((file) => file.endsWith(".css"));
      const css = readFileSync(join(assetsDir, cssFile), "utf8");

      // Cascade layers resolve by declaration order in the top `@layer`
      // statement (theme, base, components, utilities), not by which block
      // appears later in the file — but the safelisted utility must actually
      // be *inside* the utilities layer for that precedence to apply to it.
      const utilitiesLayerBody = extractLayerBody(css, "utilities");
      const componentsLayerBody = extractLayerBody(css, "components");
      expect(utilitiesLayerBody).toContain(".bg-fuchsia-950");
      expect(componentsLayerBody).toContain(".probe");
      // Preflight (base layer) legitimately uses !important elsewhere in this
      // output; what must NOT happen is the safelisted utility itself, or the
      // component default it's overriding, resorting to !important or a
      // specificity hack to win.
      const utilityRule = utilitiesLayerBody.match(/\.bg-fuchsia-950\s*{[^}]*}/)[0];
      const componentRule = componentsLayerBody.match(/\.probe\s*{[^}]*}/)[0];
      expect(utilityRule).not.toMatch(/!important/);
      expect(componentRule).not.toMatch(/!important/);
    } finally {
      rmSync(outDir, { recursive: true, force: true });
    }
  }, 30_000);
});

describe("tailwind-safelist covers both builds identically (3.4)", () => {
  it("the admin build's compiled CSS contains the full covered surface", async () => {
    const css = await compileEntry(THEME_CSS);
    // One representative class per family declared in tailwind-safelist.css —
    // not exhaustive (the size-budget test already proves the full surface
    // compiles in isolation), just proof the admin build didn't drop any
    // family on the way in.
    const representatives = [
      ".bg-red-500",
      ".p-4",
      ".flex",
      ".items-center",
      ".rounded-lg",
      ".opacity-50",
      ".text-lg",
      ".font-semibold",
      ".grid-cols-3",
    ];
    for (const rule of representatives) {
      expect(css).toContain(rule);
    }
  }, 30_000);

  it("app.css and theme.css import the exact same shared safelist partial (D2)", () => {
    // This is what actually guarantees "both bundles cover the same
    // vocabulary" — one file, two imports resolving to the same path — not
    // a coincidence that would need re-verifying every time either file
    // changes.
    const appCssDir = dirname(APP_CSS);
    const themeCssDir = dirname(THEME_CSS);
    const appCssSource = readFileSync(APP_CSS, "utf8");
    const themeCssSource = readFileSync(THEME_CSS, "utf8");

    const appImport = appCssSource.match(/@import\s+"([^"]*tailwind-safelist\.css)"/);
    const themeImport = themeCssSource.match(/@import\s+"([^"]*tailwind-safelist\.css)"/);
    expect(appImport, "app.css must import tailwind-safelist.css").not.toBeNull();
    expect(themeImport, "theme.css must import tailwind-safelist.css").not.toBeNull();

    const appResolved = pathResolve(appCssDir, appImport[1]);
    const themeResolved = pathResolve(themeCssDir, themeImport[1]);
    expect(appResolved).toBe(SAFELIST_CSS);
    expect(themeResolved).toBe(SAFELIST_CSS);
  });
});
