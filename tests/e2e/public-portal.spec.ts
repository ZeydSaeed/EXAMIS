import { expect, test } from '@playwright/test';

test('the home page is Arabic and right-to-left by default', async ({
    page,
}) => {
    await page.goto('/');

    await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        'قدّم على الامتحانات الخارجية إلكترونياً',
    );
});

test('switching to English flips the layout to left-to-right', async ({
    page,
}) => {
    await page.goto('/');
    await page.getByTestId('language-switcher').click();

    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        'Apply for external examinations online',
    );

    await page.getByTestId('language-switcher').click();
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
});

test('managed content pages render as text', async ({ page }) => {
    await page.goto('/pages/faq');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        'الأسئلة الشائعة',
    );
});

test('tracking with unknown details reveals nothing', async ({ page }) => {
    await page.goto('/track');
    await page.locator('#reference_number').fill('EX2027-0000019');
    await page.locator('#birth_date').fill('2000-01-01');
    await page.getByTestId('track-button').click();

    await expect(
        page.getByText('لم يُعثر على طلب يطابق البيانات المدخلة.', {
            exact: false,
        }),
    ).toBeVisible();
    await expect(page.getByTestId('track-result')).toHaveCount(0);
});

test('the PWA manifest, service worker and offline page are served', async ({
    page,
    request,
}) => {
    const manifest = await (await request.get('/manifest.webmanifest')).json();
    expect(manifest.short_name).toBe('EXAMIS');
    expect(manifest.display).toBe('standalone');
    expect(
        manifest.icons.some(
            (icon: { purpose: string }) => icon.purpose === 'maskable',
        ),
    ).toBe(true);

    expect((await request.get('/sw.js')).status()).toBe(200);
    expect((await request.get('/offline.html')).status()).toBe(200);

    await page.goto('/');
    const registered = await page.evaluate(async () => {
        const registration = await navigator.serviceWorker.ready;

        return registration.active?.scriptURL ?? null;
    });
    expect(registered).toContain('/sw.js');
});

test('shows the offline page for navigations when the network is down', async ({
    page,
    context,
    browserName,
}) => {
    test.skip(
        browserName !== 'chromium',
        "Playwright's offline emulation does not reliably route service-worker navigations outside Chromium.",
    );

    await page.goto('/');
    await page.evaluate(async () => navigator.serviceWorker.ready);
    await page.reload();

    await context.setOffline(true);
    await page.goto('/pages/about').catch(() => undefined);
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(
        'لا يوجد اتصال بالإنترنت',
    );
    await context.setOffline(false);
});
