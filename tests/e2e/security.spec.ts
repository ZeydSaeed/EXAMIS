import { expect, test } from '@playwright/test';
import { registerStudent, submitApplication } from './helpers';

test.describe('security boundaries', () => {
    test('another student gets 404 for an application URL', async ({
        browser,
        page,
    }) => {
        test.setTimeout(120_000);

        await submitApplication(page);
        const url = page.url();

        const other = await (await browser.newContext()).newPage();
        await registerStudent(other);

        const response = await other.goto(url);
        expect(response?.status()).toBe(404);
    });

    test('guests are sent to the terms page', async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/apply\/terms/);
    });

    test('private pages are not cacheable and carry a strict CSP', async ({
        page,
    }) => {
        await registerStudent(page);
        const response = await page.goto('/apply/specialization');

        expect(response?.headers()['cache-control']).toContain('no-store');
        expect(response?.headers()['content-security-policy']).toMatch(
            /script-src 'self' 'nonce-/,
        );
        expect(response?.headers()['x-frame-options']).toBe('DENY');
    });

    test('the service worker never stores private pages', async ({ page }) => {
        await registerStudent(page);
        await page.evaluate(async () => navigator.serviceWorker?.ready);
        await page.reload();
        await page.goto('/apply/specialization');

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
