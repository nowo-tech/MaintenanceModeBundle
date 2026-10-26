import { expect, type Page } from '@playwright/test';

/** Demo panel password (config/packages/nowo_maintenance_mode.yaml, demo only). */
export const PANEL_PASSWORD = 'maintenance';

/** Signs in to the maintenance panel through the password gate and lands on the panel index. */
export async function signIn(page: Page): Promise<void> {
  await page.goto('/_maintenance/login');
  if (!/\/_maintenance\/login/.test(page.url())) {
    // Already granted (session cookie still valid): the login route redirects to the panel.
    await expect(page.getByTestId('maintenance-status')).toBeVisible();
    return;
  }
  await page.getByLabel('Password').fill(PANEL_PASSWORD);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/_maintenance\/?$/);
  await expect(page.getByTestId('maintenance-status')).toBeVisible();
}

/** Enables maintenance from the Manual control card with an optional public message. */
export async function enableMaintenance(page: Page, message: string): Promise<void> {
  await page.goto('/_maintenance/');
  const manual = page.getByTestId('maintenance-manual');
  await manual.getByLabel('Message').fill(message);
  await manual.getByRole('button', { name: 'Enable maintenance' }).click();
  await expect(page.getByText('Maintenance enabled.')).toBeVisible();
  await expect(page.getByTestId('maintenance-status-manual')).toHaveText('ENABLED');
  await expect(page.getByTestId('maintenance-status-effective')).toHaveText('ON');
}

/** Disables maintenance from the panel (no-op assertion-wise when it is already off). */
export async function disableMaintenance(page: Page): Promise<void> {
  await page.goto('/_maintenance/');
  const status = page.getByTestId('maintenance-status-manual');
  if ((await status.textContent())?.trim() === 'ENABLED') {
    await page.getByRole('button', { name: 'Disable maintenance' }).click();
    await expect(page.getByText('Maintenance disabled.')).toBeVisible();
  }
  await expect(status).toHaveText('disabled');
  await expect(page.getByTestId('maintenance-status-effective')).toHaveText('off');
}
