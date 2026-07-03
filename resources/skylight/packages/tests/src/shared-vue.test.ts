import { describe, it, expect } from 'vitest'
import { readFileSync, existsSync } from 'node:fs'
import { resolve, dirname, basename } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Single-Vue-instance contract guard.
 *
 * The skylight build externalizes `vue` (vite.config.ts → build.rollupOptions.
 * external) and publishes ONE ESM Vue at a stable, un-hashed URL
 * (public/build/<theme>/vendor/vue.js). The shell's import-map maps the bare
 * "vue" specifier there. Third-party pre-built ESM addon widgets also
 * `import 'vue'`, so the browser resolves BOTH host and addon to the same URL —
 * and ESM caches by resolved URL — guaranteeing a single Vue runtime.
 *
 * This test proves the host side of that contract against the built artifacts:
 *   1. vendor/vue.js exists and is a real Vue ESM build exporting createApp.
 *   2. NO built app chunk bundles its own Vue (each emits a bare `from"vue"`
 *      import instead, and none contains Vue's reactivity internals).
 *
 * Requires `pnpm build` to have run first; skips cleanly otherwise so the unit
 * suite stays green without a build step.
 */
const __dirname = dirname(fileURLToPath(import.meta.url))
// packages/tests/src -> packages/tests -> packages -> <theme workspace root>
const WORKSPACE_ROOT = resolve(__dirname, '..', '..', '..')
const THEME_NAME = basename(WORKSPACE_ROOT)
const BUILD_DIR = resolve(WORKSPACE_ROOT, '..', '..', 'public', 'build', THEME_NAME)
const VENDOR_VUE = resolve(BUILD_DIR, 'vendor', 'vue.js')
const MANIFEST = resolve(BUILD_DIR, 'manifest.json')

const built = existsSync(VENDOR_VUE) && existsSync(MANIFEST)
const d = built ? describe : describe.skip

/** Every .js chunk the current manifest actually ships (ignores stale hashes). */
function shippedChunks(): string[] {
  const manifest = JSON.parse(readFileSync(MANIFEST, 'utf8')) as Record<
    string,
    { file?: string; imports?: string[]; dynamicImports?: string[] }
  >
  const files = new Set<string>()
  for (const entry of Object.values(manifest)) {
    if (entry.file?.endsWith('.js')) files.add(entry.file)
  }
  return [...files]
}

d('shared single Vue (built artifacts)', () => {
  it('publishes one ESM Vue at vendor/vue.js exporting createApp', () => {
    const src = readFileSync(VENDOR_VUE, 'utf8')
    expect(src).toMatch(/vue v3\./)
    expect(src).toMatch(/createApp/)
  })

  it('no shipped chunk bundles its own Vue (all use the externalized import)', () => {
    const chunks = shippedChunks()
    expect(chunks.length).toBeGreaterThan(0)
    let sawExternalImport = false
    for (const f of chunks) {
      const src = readFileSync(resolve(BUILD_DIR, f), 'utf8')
      // Vue's reactivity runtime marker — present only if Vue was bundled in.
      expect(src, `${f} appears to bundle Vue`).not.toContain('__v_isRef')
      if (/from["']vue["']/.test(src)) sawExternalImport = true
    }
    expect(sawExternalImport, 'expected at least one shipped chunk to import external vue').toBe(
      true,
    )
  })
})
