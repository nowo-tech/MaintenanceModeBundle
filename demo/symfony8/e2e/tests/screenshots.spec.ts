import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { disableMaintenance, enableMaintenance, signIn } from './helpers';

/**
 * REQ-DEMO-013 — README screenshots in full demo context: the demo platform shell + bundle UI (Symfony web debug
 * toolbar hidden). Captured as PNG; `make demo-screenshots` converts them to WebP and deletes the PNGs.
 */
test.describe.configure({ mode: 'serial' });

const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

const WIDTH = 1280;
const MESSAGE = 'Upgrading the catalogue database — back in about 30 minutes.';

/**
 * Resizes the viewport so the shell (or the standalone page) fits, with the Symfony web debug toolbar hidden (it is not
 * part of the demo), then captures the viewport.
 */
async function captureDemoFrame(page: Page, file: string): Promise<void> {
  await page.addStyleTag({ content: '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"]{display:none!important}' });
  // Bottom of the bundle UI (#nowo-ui-content, or the page body for the standalone 503 page).
  const contentBottom = await page
    .locator('#nowo-ui-content, body')
    .first()
    .evaluate((el) => el.getBoundingClientRect().bottom + window.scrollY);
  await page.setViewportSize({ width: WIDTH, height: Math.min(1400, Math.max(700, Math.ceil(contentBottom + 48))) });
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
    await expect(page.getByTestId('demo-platform')).toBeVisible();
    await captureDemoFrame(page, 'panel.png');
  });

  test('public 503 page — what visitors see while maintenance is on', async ({ browser }) => {
    const visitor = await browser.newContext({ viewport: { width: WIDTH, height: 900 } });
    const page = await visitor.newPage();
    const response = await page.goto('/');
    expect(response?.status()).toBe(503);
    await expect(page.getByTestId('maintenance-page')).toContainText(MESSAGE);
    await captureDemoFrame(page, 'maintenance-page.png');
    await visitor.close();
  });
});
