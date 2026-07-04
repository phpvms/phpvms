import { test, expect, type Page } from "@playwright/test";
import { mkdirSync } from "node:fs";
import { resolve } from "node:path";

/** Logbook (PIREP list) + PIREP detail SPA pages render from the generated DTOs. */
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

test("logbook list + pirep detail render", async ({ page }) => {
  const errors: string[] = [];
  page.on("console", (m) => m.type() === "error" && errors.push(m.text()));
  page.on("pageerror", (e) => errors.push("PAGEERROR: " + e.message));

  await login(page);

  // List
  await page.goto("/flights/../pireps"); // stay in-SPA via nav would also work
  await page.goto("/pireps");
  const firstCard = page.locator(".card").first();
  await expect(firstCard).toBeVisible({ timeout: 15_000 });
  await page.screenshot({ path: resolve(SHOTS, "pireps-list.png"), fullPage: true });

  // Detail (click the first card → SPA visit)
  await firstCard.click();
  await expect(page).toHaveURL(/\/pireps\/[^/]+$/);
  await expect(page.locator(".hero .ident")).toBeVisible({ timeout: 15_000 });
  await expect(page.locator(".strip .cell").first()).toBeVisible();
  await page.screenshot({ path: resolve(SHOTS, "pirep-detail.png"), fullPage: true });

  expect(errors, "no console errors\n" + errors.join("\n")).toEqual([]);
});
