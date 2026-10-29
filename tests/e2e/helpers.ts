import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * Browser projects share one database per run, so each project gets its own
 * block of synthetic applicants (seeded by EndToEndSeeder).
 */
const PROJECT_OFFSETS: Record<string, number> = {
    chromium: 0,
    'mobile-chromium': 30,
    firefox: 60,
    webkit: 90,
};

function projectOffset(): number {
    return PROJECT_OFFSETS[test.info().project.name] ?? 0;
}

export const applicant = (number: number) =>
    `e2e.applicant${String(number + projectOffset()).padStart(3, '0')}`;

/** Display name seeded for the applicant with the same number. */
export const applicantName = (number: number) =>
    `طالب اختبار ${number + projectOffset()}`;

/** A username unique to the current browser project, for registration tests. */
export const uniqueUsername = (base: string) =>
    `${base}.${test.info().project.name.replace(/[^a-z]/g, '')}`;

export const PASSWORD = 'password';

export const BIRTH_DATE = '2006-03-15';

export function pdfFile(name = 'document.pdf') {
    const body =
        '%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n';

    return { name, mimeType: 'application/pdf', buffer: Buffer.from(body) };
}

export function pngFile(name = 'photo.png') {
    const onePixel =
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    return {
        name,
        mimeType: 'image/png',
        buffer: Buffer.from(onePixel, 'base64'),
    };
}

export async function login(
    page: Page,
    username: string,
    password = PASSWORD,
): Promise<void> {
    await page.goto('/login');
    await page.locator('#username').fill(username);
    await page.locator('#password').fill(password);
    await page.getByTestId('login-button').click();
    await expect(page).not.toHaveURL(/\/login/);
}

export async function logout(page: Page): Promise<void> {
    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await expect(page).toHaveURL(/\/$/);
}

/**
 * Fills the form wizard with valid synthetic data and uploads every
 * required document, ending on the review step.
 */
export async function completeApplicationForm(page: Page): Promise<void> {
    await page.locator('#student_name').fill('سارة');
    await page.locator('#father_name').fill('علي');
    await page.locator('#grandfather_name').fill('حسين');
    await page.locator('#great_grandfather_name').fill('كاظم');
    await page.locator('#mother_first_name').fill('زينب');
    await page.locator('#mother_father_name').fill('كاظم');
    await page.locator('#birth_date').fill(BIRTH_DATE);
    await page.locator('#gender').selectOption('female');
    await page.locator('#national_id_number').fill('199812345678');
    await page.getByTestId('next-step').click();

    await expect(page.locator('#district')).toBeVisible();
    await page.locator('#district').fill('حي الحسين');
    await page.locator('#mahalla').fill('630');
    await page.locator('#alley').fill('44');
    await page.locator('#house_number').fill('164');
    // The mobile number is the account's and read-only on the form.
    await page.getByTestId('next-step').click();

    await expect(page.locator('#school_name')).toBeVisible();
    await page.locator('#school_name').fill('إعدادية الفردوس للبنات');
    await page.locator('#school_governorate_code').selectOption('KRB');
    // The branch and specialization are chosen on their own page.
    await page.getByTestId('next-step').click();

    await uploadDocument(page, 'participation_request', pdfFile('request.pdf'));
    await uploadDocument(page, 'national_id', pdfFile('id.pdf'));
    await uploadDocument(page, 'father_id', pdfFile('father.pdf'));
    await uploadDocument(page, 'mother_id', pdfFile('mother.pdf'));
    await uploadDocument(page, 'residence_card', pdfFile('residence.pdf'));
    await uploadDocument(page, 'personal_photo', pngFile());

    await page.getByTestId('next-step').click();
    await expect(page.getByTestId('submit-application')).toBeVisible();
}

export async function uploadDocument(
    page: Page,
    type: string,
    file: ReturnType<typeof pdfFile>,
): Promise<void> {
    await page.getByTestId(`file-${type}`).setInputFiles(file);
    await expect(
        page.getByTestId(`document-${type}`).getByText(file.name),
    ).toBeVisible();
}

/**
 * Logs in as the given applicant and submits a complete application.
 * Returns the issued reference number and the application URL.
 */
export async function submitApplicationAs(
    page: Page,
    username: string,
): Promise<{ reference: string; url: string }> {
    await login(page, username);
    await page.getByTestId('start-application').first().click();
    await expect(page).toHaveURL(/\/applications\/[0-9a-f-]+\/edit/);
    await completeApplicationForm(page);
    await page.locator('#declaration').check();
    await page.getByTestId('submit-application').click();
    await expect(page.getByTestId('submission-confirmed')).toBeVisible();

    const reference =
        (await page.getByTestId('reference-number').textContent())?.trim() ??
        '';

    return { reference, url: page.url() };
}
