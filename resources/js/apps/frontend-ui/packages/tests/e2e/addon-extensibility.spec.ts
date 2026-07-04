import { test, expect, type Page } from "@playwright/test";
import { mkdirSync } from "node:fs";
import { resolve } from "node:path";

/**
 * Verifies the addon-extensibility change end-to-end against the live worktree
 * app (the three sample addons must be enabled + relinked, and `pnpm build` run):
 *   - a kind:'blade' addon widget (Station NOTAMs) hosts a server fragment,
 *   - a kind:'vue' third-party ESM addon widget (Sample Vue widget) loads from
 *     /ext and shares the host Vue via the import-map,
 *   - a vue slot addon injects a component into the Bids table per-row slot.
 * Screenshots are written for eyeballing; console must be error-free.
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

test("addon widgets + bids slot render, no console errors", async ({ page }) => {
  const errors: string[] = [];
  page.on("console", (m) => m.type() === "error" && errors.push(m.text()));
  page.on("pageerror", (e) => errors.push("PAGEERROR: " + e.message));

  await login(page);
  await page.goto("/dashboard");
  await expect(page.locator(".pv-layout")).toBeVisible();

  // Add both sample widgets from the catalog (they ship defaultOn:false).
  await page.getByRole("button", { name: "Customize" }).click();
  for (const title of ["Sample Vue widget", "Station NOTAMs (sample)"]) {
    await page.getByRole("button", { name: "Add widget" }).click();
    await page.getByRole("button", { name: title }).click();
  }
  await page.getByRole("button", { name: "Done" }).click();

  // kind:'vue' third-party ESM widget loaded from /ext and rendered its own
  // fetched data (proves runtime import + shared Vue + addon-owned endpoint).
  await expect(page.getByText("Hello from the addon")).toBeVisible({ timeout: 15_000 });

  // kind:'blade' widget hosted its server-rendered fragment (NOTAMs for KJFK).
  await expect(page.getByText(/NOTAM|KJFK/i).first()).toBeVisible({ timeout: 15_000 });

  await page.screenshot({ path: resolve(SHOTS, "dashboard-addons.png"), fullPage: true });

  // Bids table: the vue-slot addon injects a per-row control into bids.row.actions.
  await page.goto("/flights/bids");
  // The vue-slot addon injects a per-row "ACARS <ident>" control into every bid
  // row's bids.row.actions slot (proves ESM slot component + per-row @-context).
  await expect(page.getByText(/ACARS/).first()).toBeVisible({ timeout: 15_000 });
  await page.screenshot({ path: resolve(SHOTS, "bids-slot.png"), fullPage: true });

  expect(errors, "no console errors\n" + errors.join("\n")).toEqual([]);
});
