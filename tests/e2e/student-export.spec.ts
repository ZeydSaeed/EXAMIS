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
    await expect(staffPage.getByRole('cell', { name: 'جواد' })).toBeVisible();

    // A range with no applications empties the list, and can still be left.
    await staffPage.getByLabel('من تاريخ').fill('2000-01-01');
    await staffPage.getByLabel('إلى تاريخ').fill('2000-01-31');
    await expect(staffPage.getByText('عدد الطلاب: 0')).toBeVisible();
    await expect(staffPage.getByTestId('export-range')).toBeVisible();

    const download = staffPage.waitForEvent('download');
    await staffPage.getByTestId('export-all').click();

    expect((await download).suggestedFilename()).toMatch(/^students-.*\.xlsx$/);
});
