import { test, expect, type Page } from '@playwright/test'

const CREDS = { email: 'admin@phpvms.net', password: 'admin' }

async function login(page: Page) {
  await page.goto('/login')
  await page.fill('input[name="email"]', CREDS.email)
  await page.fill('input[name="password"]', CREDS.password)
  await Promise.all([
    page.waitForURL('**/dashboard', { timeout: 15_000 }),
    page.click('button[type="submit"], input[type="submit"]'),
  ])
}

test.describe('skylight dashboard (Workspace SPA theme)', () => {
  test('renders the Workspace board from the DTO, customizes, no console errors', async ({ page }) => {
    const errors: string[] = []
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()))
    page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message))

    await login(page)
    await page.goto('/dashboard')

    // Shell hydrated.
    await expect(page.locator('.pv-layout')).toBeVisible()
    await expect(page.locator('.pv-region-nav a[href="/dashboard"]')).toHaveClass(/active/)

    // Widget board: the Nav-display widget mounts a MapLibre canvas + KPI widget.
    await expect(page.locator('.maplibregl-canvas')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('Total hours')).toBeVisible()
    await expect(page.locator('.wx')).toBeVisible() // weather widget

    // Customize mode: reveals the Add-widget menu + frame edit affordances.
    await page.getByRole('button', { name: 'Customize' }).click()
    await expect(page.getByRole('button', { name: 'Add widget' })).toBeVisible()
    const framesEditing = await page.locator('.frame.editing').count()
    expect(framesEditing, 'frames enter edit mode').toBeGreaterThan(0)
    await page.getByRole('button', { name: 'Done' }).click()
    await expect(page.locator('.frame.editing')).toHaveCount(0)

    // Shared-props layer (HandleInertiaRequests): auth.user + appName present.
    const shared = await page.evaluate(() => {
      const raw =
        document.querySelector('script[data-page]')?.textContent ||
        (document.querySelector('#app[data-page]') as HTMLElement | null)?.dataset.page
      return raw ? JSON.parse(raw).props : null
    })
    expect(shared?.auth?.user?.name, 'shared auth.user.name').toBeTruthy()
    expect(shared?.appName, 'shared appName').toBeTruthy()

    await page.waitForTimeout(2000)
    await page.screenshot({ path: 'test-results/dashboard.png', fullPage: true })

    expect(errors, 'no console/page errors').toEqual([])
  })

  test('navigates dashboard → profile as an SPA visit (persistent layout, no full reload)', async ({ page }) => {
    const errors: string[] = []
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()))
    page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message))

    await login(page)
    await page.goto('/dashboard')

    // Marker on window survives an Inertia visit, but not a full page reload.
    await page.evaluate(() => ((window as Window & { __spa?: boolean }).__spa = true))

    await page.locator('.pv-region-nav a[href="/profile"]').click()
    await page.waitForURL('**/profile')

    // Same persistent shell (nav rail still mounted) + profile content rendered.
    await expect(page.locator('.pv-layout')).toBeVisible()
    await expect(page.getByText('PILOT PROFILE')).toBeVisible()
    await expect(page.getByText('RECORD')).toBeVisible()

    // Proof it was a client-side visit, not a document reload.
    const survived = await page.evaluate(() => (window as Window & { __spa?: boolean }).__spa === true)
    expect(survived, 'client-side (SPA) navigation, not full reload').toBe(true)

    // Active nav moved to PROF.
    await expect(page.locator('.pv-region-nav a[href="/profile"]')).toHaveClass(/active/)

    await page.screenshot({ path: 'test-results/profile.png', fullPage: true })
    expect(errors, 'no console/page errors').toEqual([])
  })

  test('flights page lists the real schedule from the DTO via SPA nav', async ({ page }) => {
    const errors: string[] = []
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()))
    page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message))

    await login(page)
    await page.goto('/dashboard')
    await page.locator('.pv-region-nav a[href="/flights"]').click()
    await page.waitForURL('**/flights**')

    await expect(page.getByText('FLIGHTS · SCHEDULE')).toBeVisible()
    // At least one flight strip with a callsign rendered from the paginated DTO.
    expect(await page.locator('.strip .callsign').count()).toBeGreaterThan(0)
    await expect(page.locator('.pv-region-nav a[href="/flights"]')).toHaveClass(/active/)

    expect(errors, 'no console/page errors').toEqual([])
  })
})
