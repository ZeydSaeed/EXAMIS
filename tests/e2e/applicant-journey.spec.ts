import { expect, test } from '@playwright/test';
import {
    completeApplicationForm,
    fillUpToDocuments,
    pngFile,
    registerStudent,
    sendCompletedForm,
    startApplication,
    uploadDocument,
} from './helpers';

test.describe('student journey', () => {
    test('registers with a mobile number after accepting the terms', async ({
        page,
    }) => {
        await registerStudent(page);

        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    });

    test('cannot register before accepting the terms', async ({ page }) => {
        await page.goto('/register');

        await expect(page).toHaveURL(/\/apply\/terms/);
    });

    test('corrects, sends and prints an application, then the account closes', async ({
        page,
    }) => {
        test.setTimeout(120_000);

        await startApplication(page);

        // The form names what is wrong and stays on the step.
        await page.locator('#student_name').fill('Sara');
        await page.getByTestId('next-step').click();
        await expect(page.getByTestId('form-notice')).toBeVisible();
        await page.getByTestId('form-notice-close').click();

        await completeApplicationForm(page);
        await sendCompletedForm(page);

        // The account closes only after the form has arrived and the student says so.
        await page.getByTestId('printed-form-download').click();
        await page.getByTestId('confirm-action').click();
        await expect(page).not.toHaveURL(/\/applications\//);
    });

    test('blocks a file type that the document does not allow', async ({
        page,
    }) => {
        await startApplication(page);
        await fillUpToDocuments(page);

        await page.getByTestId('file-personal_photo').setInputFiles({
            name: 'notes.txt',
            mimeType: 'text/plain',
            buffer: Buffer.from('hello'),
        });
        await expect(
            page.getByText('نوع الملف غير مسموح لهذا المستند.'),
        ).toBeVisible();

        await uploadDocument(page, 'personal_photo', pngFile('portrait.png'));
        await expect(page.getByTestId('document-personal_photo')).toContainText(
            'مقبول',
        );
    });

    test('names the missing document before the review step', async ({
        page,
    }) => {
        await startApplication(page);
        await fillUpToDocuments(page);
        await page.getByTestId('next-step').click();

        await expect(page.getByTestId('form-notice')).toBeVisible();
    });
});
