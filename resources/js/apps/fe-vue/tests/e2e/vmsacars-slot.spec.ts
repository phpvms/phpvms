import { test, expect, type Page } from "@playwright/test";
import { mkdirSync } from "node:fs";
import { resolve } from "node:path";

/**
 * Verifies the external vmsACARS plugin's skylight bids-row slot end-to-end:
 *   - the plugin (mounted as modules/VMSAcars, enabled + relinked) injects a
 *     "Fly" control into the per-row `bids.row.actions` slot on /flights/bids,
 *   - the control is a runtime-imported ESM from /ext/vmsacars/widgets/fly.js
 *     that shares the host Vue via the import-map,
 *   - it computes the correct `vmsacars:/bid/<id>` deep link from the per-row
 *     @-context (exposed as data-href for deterministic assertion).
 */
const CREDS = { email: "admin@phpvms.net", password: "admin" };
const SHOTS = resolve(process.cwd(), ".verify");
mkdirSync(SHOTS, { recursive: true });

async function login(page: Page) {
  await page.goto("/login");
  await page.fill('input[name="email"]', CREDS.email);
  await page.fill('input[name="password"]', CREDS.password);
  await Promise.all([
    page.waitForURL("**/dashboard", { timeout: 15_000 }),
    page.click('button[type="submit"], input[type="submit"]'),
  ]);
}

test("vmsACARS bids slot renders and builds the deep link", async ({ page }) => {
  const errors: string[] = [];
  page.on("console", (m) => m.type() === "error" && errors.push(m.text()));
  page.on("pageerror", (e) => errors.push("PAGEERROR: " + e.message));

  await login(page);
  await page.goto("/flights/bids");

  const fly = page.getByTestId("vmsacars-fly").first();
  await expect(fly).toBeVisible({ timeout: 15_000 });

  // The deep link must be the single-slash form the desktop client's AppArgs
  // parser accepts, carrying this row's bid id (or flight id fallback).
  const href = await fly.getAttribute("data-href");
  expect(href).toMatch(/^vmsacars:\/(bid|flight)\/[^/]+$/);

  await page.screenshot({ path: resolve(SHOTS, "vmsacars-bids-slot.png"), fullPage: true });
  expect(errors, "no console errors\n" + errors.join("\n")).toEqual([]);
});
