import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { applicant, login } from './helpers';

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth - window.innerWidth,
    );
    expect(overflow).toBeLessThanOrEqual(1);
}

for (const path of [
    '/',
    '/login',
    '/register',
    '/track',
    '/pages/instructions',
]) {
    test(`${path} fits a phone screen without horizontal scrolling`, async ({
        page,
    }) => {
        await page.goto(path);
        await expectNoHorizontalOverflow(page);
    });
}

test('the application form fits a phone screen', async ({ page }) => {
    await login(page, applicant(11));
    await page.getByTestId('start-application').first().click();
    await expect(page.locator('#full_name_ar')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    // Long Arabic names must wrap, not overflow.
    await page
        .locator('#full_name_ar')
        .fill('عبد الرحمن محمد عبد الكريم عبد الحسين الساعدي الموسوي');
    await expectNoHorizontalOverflow(page);
});
