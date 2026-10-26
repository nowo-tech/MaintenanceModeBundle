import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { disableMaintenance, enableMaintenance, signIn } from './helpers';

/**
 * REQ-DEMO-013 — README screenshots in full demo context: demo masthead + bundle UI + Symfony web debug
 * toolbar. Captured as PNG; `make demo-screenshots` converts them to WebP and deletes the PNGs.
 */
test.describe.configure({ mode: 'serial' });

const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

const WIDTH = 1280;
const MESSAGE = 'Upgrading the catalogue database — back in about 30 minutes.';

/**
 * Resizes the viewport so masthead, bundle UI and the (fixed, bottom) web debug toolbar all fit,
 * then captures the viewport: a padded union of header → main → .sf-toolbar without empty gaps.
 */
async function captureDemoFrame(page: Page, file: string): Promise<void> {
  const toolbar = page.locator('.sf-toolbar .sf-toolbarreset');
  await expect(toolbar).toBeVisible();
  const toolbarHeight = (await toolbar.boundingBox())?.height ?? 40;
  // Bottom of the bundle UI (#nowo-ui-content), not <main>, which may stretch to the viewport height.
  const contentBottom = await page
    .locator('#nowo-ui-content')
    .evaluate((el) => el.getBoundingClientRect().bottom + window.scrollY);
  await page.setViewportSize({ width: WIDTH, height: Math.ceil(contentBottom + 48 + toolbarHeight) });
  await expect(toolbar).toBeVisible();
  await page.screenshot({ path: resolve(outDir, file) });
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('MaintenanceMode screenshots (full demo frame)', () => {
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage();
    await signIn(page);
    await disableMaintenance(page);
    await page.close();
  });

  test('panel — maintenance enabled with an operator message', async ({ page }) => {
    await page.setViewportSize({ width: WIDTH, height: 900 });
    await signIn(page);
    await disableMaintenance(page);
    await enableMaintenance(page, MESSAGE);
    await expect(page.locator('header.demo-masthead')).toBeVisible();
    await captureDemoFrame(page, 'panel.png');
  });

  test('public 503 page — what visitors see while maintenance is on', async ({ browser }) => {
    const visitor = await browser.newContext({ viewport: { width: WIDTH, height: 900 } });
    const page = await visitor.newPage();
    const response = await page.goto('/');
    expect(response?.status()).toBe(503);
    await expect(page.getByTestId('maintenance-page')).toContainText(MESSAGE);
    await expect(page.locator('header.demo-masthead')).toBeVisible();
    await captureDemoFrame(page, 'maintenance-page.png');
    await visitor.close();
  });
});
