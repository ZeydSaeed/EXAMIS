import { expect, test } from '@playwright/test';
import { staffURL } from '../../playwright.config';
import { staffLogin, submitApplication } from './helpers';

test('a supervisor reviews a student application and accepts it', async ({
    browser,
    page,
}) => {
    test.setTimeout(180_000);

    const studentNumber = await submitApplication(page);

    const staffPage = await (await browser.newContext()).newPage();
    await staffLogin(staffPage, 'supervisor');
    await staffPage.goto(`${staffURL}/admin/applications?q=${studentNumber}`);

    await staffPage.getByTestId('transition-under_review').click();
    await staffPage.getByTestId('confirm-action').click();
    await expect(
        staffPage.getByTestId('transition-requires_correction'),
    ).toBeVisible();

    // A correction needs a reason.
    await staffPage.getByTestId('transition-requires_correction').click();
    await staffPage.getByTestId('confirm-action').click();
    await expect(
        staffPage.getByText('يجب كتابة السبب لهذا الإجراء.'),
    ).toBeVisible();
    await staffPage.keyboard.press('Escape');

    await staffPage.getByTestId('transition-accepted').click();
    await staffPage.getByTestId('confirm-action').click();
    await expect(staffPage.getByText('مقبول').first()).toBeVisible();
});

test('a reviewer cannot see the decision buttons', async ({
    browser,
    page,
}) => {
    test.setTimeout(180_000);

    const studentNumber = await submitApplication(page);

    const staffPage = await (await browser.newContext()).newPage();
    await staffLogin(staffPage, 'reviewer');
    await staffPage.goto(`${staffURL}/admin/applications?q=${studentNumber}`);
    await staffPage.getByTestId('transition-under_review').click();
    await staffPage.getByTestId('confirm-action').click();

    await expect(
        staffPage.getByTestId('transition-requires_correction'),
    ).toBeVisible();
    await expect(staffPage.getByTestId('transition-accepted')).toHaveCount(0);
});

test('the header arrows move back and forward through page history', async ({
    page,
}) => {
    await staffLogin(page, 'supervisor');
    await page.goto(`${staffURL}/admin`);

    await page.getByTestId('stat-submitted').click();
    await expect(page).toHaveURL(/\/admin\/applications/);

    await page.getByTestId('history-back').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.getByTestId('history-forward').click();
    await expect(page).toHaveURL(/\/admin\/applications/);
});
