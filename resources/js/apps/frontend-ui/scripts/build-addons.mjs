#!/usr/bin/env node
/**
 * Core-build addon widget discovery.
 *
 * Globs for in-repo addon widget build configs and runs `vite build` for each,
 * sequentially. First-party addons ship their widget source in this repo and
 * expose a build config at:
 *
 *     modules/<Name>/ui/vite.config.{ts,mjs,js}
 *
 * …which imports the widget preset (resources/<theme>/addon-build/widget-preset.ts)
 * and outputs a pre-built ESM widget into the addon's public/ dir. Third-party
 * addons build in their own repos and are not discovered here.
 *
 * Rename-safe: all paths are resolved relative to THIS file's location, so the
 * theme workspace can be renamed from "skylight" without touching this script.
 * No-ops cleanly (exit 0) when zero addon configs exist.
 */
import { spawnSync } from 'node:child_process'
import { existsSync, readdirSync, statSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const SCRIPT_DIR = dirname(fileURLToPath(import.meta.url))
// resources/js/apps/frontend-ui/scripts/ -> resources/js/apps/frontend-ui/
const WORKSPACE_ROOT = resolve(SCRIPT_DIR, '..')
// workspace -> apps -> js -> resources -> repo root -> modules/
const MODULES_DIR = resolve(WORKSPACE_ROOT, '..', '..', '..', '..', 'modules')
// The Vite binary in this workspace's node_modules.
const VITE_BIN = resolve(WORKSPACE_ROOT, 'node_modules', '.bin', 'vite')

const CONFIG_NAMES = ['vite.config.ts', 'vite.config.mjs', 'vite.config.js']

/**
 * Discover addon widget configs: modules/<Name>/ui/vite.config.{ts,mjs,js}.
 * Tolerates a missing modules/ dir and zero matches without erroring.
 */
function discoverConfigs() {
  if (!existsSync(MODULES_DIR)) return []

  const configs = []
  for (const entry of readdirSync(MODULES_DIR)) {
    const uiDir = resolve(MODULES_DIR, entry, 'ui')
    if (!existsSync(uiDir) || !statSync(uiDir).isDirectory()) continue
    for (const name of CONFIG_NAMES) {
      const candidate = resolve(uiDir, name)
      if (existsSync(candidate)) {
        configs.push(candidate)
        break // one config per addon
      }
    }
  }
  return configs.sort()
}

function main() {
  const configs = discoverConfigs()

  if (configs.length === 0) {
    console.log('[build-addons] No addon widget configs found — nothing to build.')
    return
  }

  console.log(`[build-addons] Found ${configs.length} addon widget config(s):`)
  for (const c of configs) console.log(`[build-addons]   - ${c}`)

  for (const config of configs) {
    console.log(`[build-addons] Building: ${config}`)
    const result = spawnSync(VITE_BIN, ['build', '-c', config], {
      stdio: 'inherit',
      cwd: WORKSPACE_ROOT,
    })
    if (result.status !== 0) {
      console.error(`[build-addons] FAILED: ${config} (exit ${result.status})`)
      process.exit(result.status ?? 1)
    }
  }

  console.log(`[build-addons] Done — built ${configs.length} addon widget bundle(s).`)
}

main()
