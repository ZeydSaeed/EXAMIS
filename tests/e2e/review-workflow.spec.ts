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

    // The dashboard's cards follow the five statuses staff see (no "submitted").
    await page.getByTestId('stat-under_review').click();
    await expect(page).toHaveURL(/\/admin\/applications/);

    await page.getByTestId('history-back').click();
    await expect(page).toHaveURL(/\/admin$/);

    await page.getByTestId('history-forward').click();
    await expect(page).toHaveURL(/\/admin\/applications/);
});

test('the applications list shows the agreed columns and decides in place', async ({
    browser,
    page,
}) => {
    test.setTimeout(180_000);

    const studentNumber = await submitApplication(page);

    const context = await browser.newContext();
    const staffPage = await context.newPage();
    await staffLogin(staffPage, 'supervisor');
    await staffPage.goto(
        `${staffURL}/admin/applications?status=submitted&per_page=100`,
    );

    const headers = await staffPage.locator('thead th').allTextContents();
    expect(headers.map((header) => header.trim())).toEqual([
        'ت',
        'رقم الطالب',
        'اسم الطالب الرباعي',
        'الحالة',
        'رقم الموبايل',
        'معاينة',
    ]);

    // The preview opens the student's form in its own tab.
    const preview = staffPage.getByTestId(`preview-${studentNumber}`);
    await expect(preview).toHaveAttribute('target', '_blank');

    const contentType = await preview.evaluate(
        async (link: HTMLAnchorElement) =>
            (await fetch(link.href)).headers.get('content-type'),
    );
    expect(contentType).toContain('application/pdf');

    const status = staffPage.getByTestId(`status-${studentNumber}`);
    // A sent application shows as "under review"; staff may start the review
    // or decide straight away.
    await expect(status.locator('option')).toHaveText([
        'قيد التدقيق',
        'بدء التدقيق',
        'مقبول',
        'مرفوض',
        'وجود نقص بالمعاملة',
    ]);

    // The ready-made message asks staff to fill in what is missing.
    await status.selectOption('requires_correction');
    const dialog = staffPage.getByRole('dialog');
    await expect(dialog.locator('textarea')).toHaveValue(
        new RegExp(`رقم الطالب: ${studentNumber}`),
    );

    await staffPage.getByTestId('confirm-action').click();
    await expect(
        staffPage.getByText('أكمل الفراغات في الرسالة قبل الإرسال.'),
    ).toBeVisible();

    await staffPage.getByTestId('gap-0').fill('صورة بطاقة السكن');

    // More missing items than the template has room for keep the numbering.
    await staffPage.getByTestId('gap-1').fill('شهادة الجنسية');
    await staffPage.getByTestId('gap-2').fill('صورة شخصية');
    await staffPage.getByTestId('add-gap').click();
    await staffPage.getByTestId('gap-3').fill('البطاقة الموحدة');
    await expect(dialog.locator('textarea')).toHaveValue(/4\. البطاقة الموحدة/);

    await staffPage.getByTestId('confirm-action').click();

    // Nothing is sent by the system: staff send it from their own WhatsApp.
    const message = staffPage.getByTestId('decision-message');
    await expect(message).toContainText('صورة بطاقة السكن');
    await expect(message).not.toContainText('{فراغ}');
    await expect(staffPage.getByTestId('whatsapp-link')).toHaveAttribute(
        'href',
        /^https:\/\/wa\.me\/964\d+\?text=/,
    );

    // The student's number, and a prompt as this account has none yet.
    const numbers = staffPage.getByTestId('message-numbers');
    await expect(numbers).toContainText('+964');
    await expect(numbers).toContainText('لا يوجد رقم موبايل لحسابك');
});
