import { expect, test } from '@playwright/test';
import {
    applicant,
    login,
    logout,
    pdfFile,
    submitApplicationAs,
    uploadDocument,
} from './helpers';

test('a correction request goes to the applicant and the corrected application is accepted', async ({
    page,
}) => {
    // Three sign-ins and two review rounds: longer than the default budget.
    test.setTimeout(180_000);

    const { reference, url } = await submitApplicationAs(page, applicant(9));
    await logout(page);

    // Supervisor starts the review and asks for a correction.
    await login(page, 'supervisor');
    await expect(page).toHaveURL(/\/admin/);
    await page.goto(`/admin/applications?q=${reference}`);
    await page.getByRole('link', { name: reference }).click();

    await page.getByTestId('transition-under_review').click();
    await page.getByTestId('confirm-action').click();
    await expect(
        page.getByTestId('transition-requires_correction'),
    ).toBeVisible();

    await page.getByTestId('transition-requires_correction').click();
    await page.getByTestId('confirm-action').click();
    await expect(page.getByText('يجب كتابة السبب لهذا الإجراء.')).toBeVisible();
    await page
        .locator('#reason-requires_correction')
        .fill('صورة وثيقة المدرسة غير واضحة، يرجى إعادة رفعها.');
    await page.getByTestId('confirm-action').click();
    await expect(page.getByText('يتطلب استكمالاً').first()).toBeVisible();
    await logout(page);

    // Applicant reads the note, replaces the document and resubmits.
    await login(page, applicant(9));
    await page.goto(url);
    await expect(
        page
            .getByText('صورة وثيقة المدرسة غير واضحة، يرجى إعادة رفعها.')
            .first(),
    ).toBeVisible();
    await page.getByRole('link', { name: 'استكمال الطلب' }).click();
    await page.getByRole('button', { name: /المستمسكات/ }).click();
    await uploadDocument(
        page,
        'school_certificate',
        pdfFile('school-clear.pdf'),
    );
    await page.getByTestId('next-step').click();
    await page.locator('#declaration').check();
    await page.getByTestId('submit-application').click();
    await expect(page.getByText('أُعيد إرساله').first()).toBeVisible();
    await logout(page);

    // Supervisor reviews again and accepts.
    await login(page, 'supervisor');
    await page.goto(`/admin/applications?q=${reference}`);
    await page.getByRole('link', { name: reference }).click();
    await page.getByTestId('transition-under_review').click();
    await page.getByTestId('confirm-action').click();
    await page.getByTestId('transition-accepted').click();
    await page.getByTestId('confirm-action').click();
    await expect(page.getByText('مقبول').first()).toBeVisible();
});

test('a reviewer cannot see the decision buttons', async ({ page }) => {
    const { reference } = await submitApplicationAs(page, applicant(10));
    await logout(page);

    await login(page, 'reviewer');
    await page.goto(`/admin/applications?q=${reference}`);
    await page.getByRole('link', { name: reference }).click();
    await page.getByTestId('transition-under_review').click();
    await page.getByTestId('confirm-action').click();

    await expect(
        page.getByTestId('transition-requires_correction'),
    ).toBeVisible();
    await expect(page.getByTestId('transition-accepted')).toHaveCount(0);
});

test('the header arrows move back and forward through page history', async ({
    page,
}) => {
    await login(page, 'supervisor');
    await expect(page).toHaveURL(/\/admin\/home$/);
    await page.goto('/admin');

    await page.getByTestId('stat-awaiting').click();
    await expect(page).toHaveURL(/\/admin\/applications/);

    await page.getByTestId('history-back').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.getByTestId('history-forward').click();
    await expect(page).toHaveURL(/\/admin\/applications/);
});
