import { expect, test } from '@playwright/test';
import { staffURL } from '../../playwright.config';
import { staffLogin, submitApplication } from './helpers';

test('a supervisor exports student information to Excel', async ({
    browser,
    page,
}) => {
    test.setTimeout(180_000);

    await submitApplication(page);

    const staffPage = await (await browser.newContext()).newPage();
    await staffLogin(staffPage, 'supervisor');
    await staffPage.goto(`${staffURL}/admin/reports`);

    await staffPage.getByRole('link', { name: 'تصدير إلى إكسل' }).click();
    await expect(
        staffPage.getByRole('columnheader', { name: 'اسم جد الأم' }),
    ).toBeVisible();
    // Earlier specs send applications with the same answers.
    await expect(
        staffPage.getByRole('cell', { name: 'جواد', exact: true }).first(),
    ).toBeVisible();

    // A range with no applications empties the list, and can still be left.
    await staffPage.getByLabel('من تاريخ').fill('2000-01-01');
    await staffPage.getByLabel('إلى تاريخ').fill('2000-01-31');
    await expect(staffPage.getByText('عدد الطلاب: 0')).toBeVisible();
    await staffPage.getByLabel('من تاريخ').fill('');
    await staffPage.getByLabel('إلى تاريخ').fill('');

    // The status filter narrows the list, and the export takes it along.
    await staffPage.getByLabel('الحالة').selectOption('pending');
    await expect(staffPage).toHaveURL(/status=pending/);
    await expect(
        staffPage.getByRole('cell', { name: 'قيد التدقيق' }).first(),
    ).toBeVisible();
    await expect(staffPage.getByTestId('export')).toHaveText('تصدير إلى إكسل');
    await expect(staffPage.getByTestId('export')).toHaveAttribute(
        'href',
        /status=pending/,
    );

    const download = staffPage.waitForEvent('download');
    await staffPage.getByTestId('export').click();

    expect((await download).suggestedFilename()).toMatch(/^students-.*\.xlsx$/);
});
