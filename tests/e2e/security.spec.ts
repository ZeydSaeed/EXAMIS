import { expect, test } from '@playwright/test';
import {
    applicant,
    applicantName,
    login,
    logout,
    submitApplicationAs,
} from './helpers';

test.describe('security boundaries', () => {
    test('another applicant gets 404 for an application URL', async ({
        page,
    }) => {
        const { url } = await submitApplicationAs(page, applicant(4));
        await logout(page);

        await login(page, applicant(5));
        const response = await page.goto(url);
        expect(response?.status()).toBe(404);
    });

    test('guests are sent to the login page', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('nothing personal is shown after logging out and going back', async ({
        page,
    }) => {
        await login(page, applicant(6));
        await expect(
            page.getByText(applicantName(6), { exact: true }).first(),
        ).toBeVisible();
        await logout(page);

        await page.goBack();
        await expect(
            page.getByText(applicantName(6), { exact: true }),
        ).toHaveCount(0);
    });

    test('private pages are not cacheable and carry a strict CSP', async ({
        page,
    }) => {
        await login(page, applicant(7));
        const response = await page.goto('/dashboard');

        expect(response?.headers()['cache-control']).toContain('no-store');
        expect(response?.headers()['content-security-policy']).toMatch(
            /script-src 'self' 'nonce-/,
        );
        expect(response?.headers()['x-frame-options']).toBe('DENY');
    });

    test('the service worker never stores private pages', async ({ page }) => {
        await login(page, applicant(8));
        await page.goto('/dashboard');
        await page.evaluate(async () => navigator.serviceWorker?.ready);
        await page.reload();
        await page.goto('/notifications');

        const cachedUrls = await page.evaluate(async () => {
            const urls: string[] = [];

            for (const name of await caches.keys()) {
                const cache = await caches.open(name);
                urls.push(
                    ...(await cache.keys()).map((request) => request.url),
                );
            }

            return urls;
        });

        // Only fingerprinted public build files and the static precache are allowed.
        const allowed =
            /\/build\/assets\/|\/(offline\.html|manifest\.webmanifest|favicon\.svg|icons\/icon-\d+\.png)$/;
        expect(cachedUrls.length).toBeGreaterThan(0);
        expect(cachedUrls.filter((url) => !allowed.test(url))).toEqual([]);
    });
});
