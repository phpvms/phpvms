import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))

/**
 * Root vitest config — runs all test files under packages/tests/src/ in jsdom
 * with @vue/test-utils. `@` aliases the SPA source tree.
 */
export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['packages/tests/src/**/*.test.ts'],
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'apps/spa'),
    },
  },
})
