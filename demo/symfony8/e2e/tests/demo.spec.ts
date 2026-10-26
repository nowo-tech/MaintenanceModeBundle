import { test, expect } from '@playwright/test';
import { disableMaintenance, enableMaintenance, signIn } from './helpers';

/**
 * REQ-DEMO-013 — maintenance mode happy path against the FrankenPHP demo:
 * password gate → enable → public 503 (exclusions stay up) → disable → site restored.
 * The maintenance state is shared (var/maintenance/state.json), so the flow runs serially
 * and always ends with maintenance disabled.
 */
test.describe.configure({ mode: 'serial' });

const MESSAGE = 'Playwright e2e: database upgrade in progress.';

/** Paths excluded from the 503 in config/packages/nowo_maintenance_mode.yaml (one per exclusion kind). */
const EXCLUDED = [
  { path: '/health', kind: 'exclusions.paths' },
  { path: '/status', kind: 'exclusions.routes (app_status)' },
  { path: '/api/ops/ping', kind: 'exclusions.path_prefixes' },
  { path: '/internal-check', kind: 'exclusions.patterns glob' },
  { path: '/ops/ready', kind: 'exclusions.patterns regex' },
  { path: '/examples', kind: 'exclusions.path_prefixes (/examples)' },
];

test.describe('MaintenanceMode demo', () => {
  test.beforeAll(async ({ browser }) => {
    // Start from a known state even if a previous run was interrupted.
    const page = await browser.newPage();
    await signIn(page);
    await disableMaintenance(page);
    await page.close();
  });

  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await signIn(page);
    await disableMaintenance(page);
    await page.close();
  });

  test('home page is served while maintenance is off', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);
    await expect(page.getByRole('heading', { level: 1, name: 'Maintenance Mode Bundle' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Demo' })).toBeVisible();
  });

  test('panel is behind the password gate', async ({ page, request }) => {
    const anonymous = await request.get('/_maintenance/', { maxRedirects: 0 });
    expect(anonymous.status()).toBe(302);
    expect(anonymous.headers()['location']).toMatch(/\/_maintenance\/login$/);

    await page.goto('/_maintenance/login');
    await page.getByLabel('Password').fill('not-the-password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByText('Invalid password.')).toBeVisible();
    await expect(page).toHaveURL(/\/_maintenance\/login$/);

    await signIn(page);
    await expect(page.getByTestId('maintenance-status-manual')).toHaveText('disabled');
  });

  test('enabling maintenance serves the 503 page with the operator message', async ({ page, browser }) => {
    await signIn(page);
    await enableMaintenance(page, MESSAGE);

    // A fresh visitor (no panel session) gets the public maintenance page.
    const visitor = await browser.newContext();
    const visitorPage = await visitor.newPage();
    const response = await visitorPage.goto('/');
    expect(response?.status()).toBe(503);
    expect(response?.headers()['retry-after']).toBeTruthy();
    const maintenance = visitorPage.getByTestId('maintenance-page');
    await expect(maintenance).toBeVisible();
    await expect(maintenance.getByRole('heading', { level: 1 })).toHaveText("We're tidying up — back soon");
    await expect(maintenance).toContainText(MESSAGE);
    // The public 503 must not point visitors at the panel login.
    await expect(visitorPage.locator('a[href*="/_maintenance"]')).toHaveCount(0);
    await visitor.close();
  });

  test('excluded routes and the panel stay reachable during maintenance', async ({ request }) => {
    expect((await request.get('/')).status()).toBe(503);
    for (const { path, kind } of EXCLUDED) {
      const response = await request.get(path);
      expect(response.status(), `${path} (${kind})`).toBe(200);
    }
    const status = await request.get('/status');
    expect(await status.json()).toMatchObject({ ok: true, via: 'exclusions.routes: app_status' });
    // The panel prefix is auto-excluded so operators can always switch maintenance off.
    expect((await request.get('/_maintenance/login')).status()).toBe(200);
  });

  test('disabling maintenance restores the site and is recorded in history', async ({ page, request }) => {
    await signIn(page);
    await disableMaintenance(page);

    const home = await request.get('/');
    expect(home.status()).toBe(200);
    expect(await home.text()).toContain('Maintenance Mode Bundle');

    await page.getByRole('link', { name: 'History' }).first().click();
    await expect(page).toHaveURL(/\/_maintenance\/history/);
    const table = page.getByRole('table');
    await expect(table).toBeVisible();
    // Newest first: the disable we just did, then the enable carrying our message.
    const [latest, previous] = [table.getByRole('row').nth(1), table.getByRole('row').nth(2)];
    await expect(latest).toContainText(/\bdisable\b/);
    await expect(latest).toContainText('panel');
    await expect(previous).toContainText(/\benable\b/);
    await expect(previous).toContainText(MESSAGE);
  });
});
