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
    await page.locator('#full_name_ar').fill('سارة علي حسين الجبوري');
    await page.locator('#mother_name_ar').fill('زينب كاظم جواد');
    await page.locator('#full_name_en').fill('Sara Ali Hussein');
    await page.locator('#birth_date').fill(BIRTH_DATE);
    await page.locator('#gender').selectOption('female');
    await page.locator('#national_id_number').fill('199812345678');
    await page.getByTestId('next-step').click();

    await expect(page.locator('#phone')).toBeVisible();
    await page.locator('#phone').fill('07701234567');
    await page.locator('#governorate_code').selectOption('BGD');
    await page.getByTestId('next-step').click();

    await expect(page.locator('#school_name')).toBeVisible();
    await page.locator('#school_name').fill('إعدادية الفردوس للبنات');
    await page.locator('#branch_code').selectOption('SCI');
    await page.locator('#category_code').selectOption('EXTERNAL');
    await page.getByTestId('next-step').click();

    await uploadDocument(page, 'personal_photo', pngFile());
    await uploadDocument(page, 'national_id', pdfFile('id.pdf'));
    await uploadDocument(page, 'school_certificate', pdfFile('school.pdf'));

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
