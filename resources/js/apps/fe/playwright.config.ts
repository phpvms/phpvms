import { defineConfig, devices } from "@playwright/test";

/**
 * E2E tests run against the live worktree Sail app (http://localhost:8080).
 * They verify the SPA theme actually hydrates in a real browser — the layer
 * vitest (jsdom) can't cover.
 */
export default defineConfig({
  testDir: "./packages/tests/e2e",
  timeout: 30_000,
  fullyParallel: false,
  reporter: [["list"]],
  use: {
    baseURL: process.env.SKYLIGHT_BASE_URL ?? "http://localhost:8080",
    headless: true,
    ignoreHTTPSErrors: true,
    viewport: { width: 1280, height: 900 },
    screenshot: "only-on-failure",
    // Software WebGL (SwiftShader) so the MapLibre globe renders headless.
    launchOptions: {
      args: ["--use-gl=angle", "--use-angle=swiftshader", "--ignore-gpu-blocklist"],
    },
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
