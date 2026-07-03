import { test, expect, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

/**
 * Disable-safety proof: with all three sample addons DISABLED (their providers
 * never boot → nothing registered), the dashboard catalog must not offer the
 * sample widgets and the bids table must show no injected slot control — with
 * zero console errors and no broken pages. Run this AFTER disabling the addons.
 */
const CREDS = { email: 'admin@phpvms.net', password: 'admin' }
const SHOTS = resolve(process.cwd(), '.verify')
mkdirSync(SHOTS, { recursive: true })

async function login(page: Page) {
  await page.goto('/login')
  await page.fill('input[name="email"]', CREDS.email)
  await page.fill('input[name="password"]', CREDS.password)
  await Promise.all([
    page.waitForURL('**/dashboard', { timeout: 15_000 }),
    page.click('button[type="submit"], input[type="submit"]'),
  ])
}

// Stateful check: only valid while the three sample addons are DISABLED. It is
// skipped in the default suite (which runs with them enabled). To run it:
//   disable the addons + reprime, then SKYLIGHT_ADDONS_DISABLED=1 playwright test addon-disabled
test.skip(!process.env.SKYLIGHT_ADDONS_DISABLED, 'run only with sample addons disabled')

test('disabled addons contribute nothing, no console errors', async ({ page }) => {
  const errors: string[] = []
  page.on('console', (m) => m.type() === 'error' && errors.push(m.text()))
  page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message))

  await login(page)
  await page.goto('/dashboard')
  await expect(page.locator('.pv-layout')).toBeVisible()

  // Neither sample widget is on the board or in the catalog.
  await expect(page.getByText('Hello from the addon')).toHaveCount(0)
  await expect(page.getByText('Station NOTAMs (sample)')).toHaveCount(0)
  await page.getByRole('button', { name: 'Customize' }).click()
  // With the samples gone and all first-party widgets already placed, there is
  // nothing left to add — the Add-widget button is disabled. (Were a sample
  // still registered, it would be addable and this would be enabled.)
  await expect(page.getByRole('button', { name: 'Add widget' })).toBeDisabled()
  await page.screenshot({ path: resolve(SHOTS, 'dashboard-disabled.png'), fullPage: true })

  // Bids table renders, but no addon slot control is injected.
  await page.goto('/flights/bids')
  await expect(page.getByText(/FLIGHT/).first()).toBeVisible({ timeout: 15_000 })
  await expect(page.getByText(/ACARS/)).toHaveCount(0)
  await page.screenshot({ path: resolve(SHOTS, 'bids-disabled.png'), fullPage: true })

  expect(errors, 'no console errors\n' + errors.join('\n')).toEqual([])
})
