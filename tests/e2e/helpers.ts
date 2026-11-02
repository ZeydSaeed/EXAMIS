import { execFileSync } from 'node:child_process';
import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { e2eEnv, staffURL, studentsURL } from '../../playwright.config';

export const BIRTH_DATE = '2006-03-15';

export const STAFF_PASSWORD = 'password';

export function pngFile(name = 'photo.png') {
    const onePixel =
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    return {
        name,
        mimeType: 'image/png',
        buffer: Buffer.from(onePixel, 'base64'),
    };
}

/** Runs an artisan command on the test database and returns its output. */
export function artisan(...args: string[]): string {
    return execFileSync('php', ['artisan', ...args, '--no-ansi'], {
        env: { ...process.env, ...e2eEnv },
        encoding: 'utf8',
    });
}

export function invitationLinkFrom(output: string): string {
    const link = output.match(/http\S+\/staff\/invitation#\S+/)?.[0];

    if (link === undefined) {
        throw new Error('The command printed no invitation link.');
    }

    return link;
}

export async function staffPassword(
    page: Page,
    email: string,
    password = STAFF_PASSWORD,
): Promise<void> {
    await page.goto(`${staffURL}/staff/login`);
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByTestId('login-button').click();
}

/** Signs in a seeded staff account. */
export async function staffLogin(
    page: Page,
    username: 'admin' | 'supervisor' | 'reviewer' | 'auditor',
): Promise<void> {
    await staffPassword(page, `${username}@examis.test`);
    await expect(page).toHaveURL(/\/admin\/home/);
}

/** Terms, then a mobile number: the only way a student account starts. */
export async function registerStudent(page: Page): Promise<void> {
    await page.goto(`${studentsURL}/apply/terms`);
    await page.getByTestId('accept-terms').click();
    await page.getByTestId('terms-next').click();
    await expect(page).toHaveURL(/\/register/);
    await page.locator('#phone').fill('07701234567');
    await page.getByTestId('register-user-button').click();
    await expect(page).toHaveURL(/\/apply\/specialization/);
}

/** From a fresh registration to the first step of the form. */
export async function startApplication(page: Page): Promise<void> {
    await registerStudent(page);

    const branch = page.getByTestId('branch-select');
    await branch.selectOption({ index: 1 });

    const specialization = page.getByTestId('specialization-select');
    await specialization.selectOption({ index: 1 });

    await page.getByTestId('specialization-next').click();
    await expect(page).toHaveURL(/\/applications\/[0-9a-f-]+\/edit/);
}

/** Fills the first three steps, ending on the documents step. */
export async function fillUpToDocuments(page: Page): Promise<void> {
    await page.locator('#student_name').fill('سارة');
    await page.locator('#father_name').fill('علي');
    await page.locator('#grandfather_name').fill('حسين');
    await page.locator('#great_grandfather_name').fill('كاظم');
    await page.locator('#mother_first_name').fill('زينب');
    await page.locator('#mother_father_name').fill('كاظم');
    await page.locator('#birth_date').fill(BIRTH_DATE);
    await page.locator('select#birth_place_code').selectOption({ index: 1 });

    // Some specializations fix the gender, which then shows but cannot change.
    if (await page.locator('#gender').isEnabled()) {
        await page.locator('#gender').selectOption('female');
    }

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

    await expect(page.getByTestId('file-personal_photo')).toBeAttached();
}

/** The whole form, ending on the review step. */
export async function completeApplicationForm(page: Page): Promise<void> {
    await fillUpToDocuments(page);

    // Only the personal photo is uploaded; the rest go in the paper file.
    await uploadDocument(page, 'personal_photo', pngFile());

    await page.getByTestId('next-step').click();
    await expect(page.getByTestId('submit-application')).toBeVisible();
}

export async function uploadDocument(
    page: Page,
    type: string,
    file: ReturnType<typeof pngFile>,
): Promise<void> {
    await page.getByTestId(`file-${type}`).setInputFiles(file);
    await expect(
        page.getByTestId(`document-${type}`).getByText(file.name),
    ).toBeVisible();
}

/**
 * Registers a student and sends a complete application. Returns the student
 * number, which staff scan or type to open the application.
 */
export async function submitApplication(page: Page): Promise<string> {
    await startApplication(page);
    await completeApplicationForm(page);
    await sendCompletedForm(page);

    return (
        (
            await page
                .getByTestId('printed-confirmation')
                .locator('bdi')
                .textContent()
        )?.trim() ?? ''
    );
}

/** The declaration, then confirming in the print dialog. */
export async function sendCompletedForm(page: Page): Promise<void> {
    await page.locator('#declaration').check();
    await page.getByTestId('submit-application').click();
    await page.getByTestId('confirm-action').click();
    await expect(page.getByTestId('printed-confirmation')).toBeVisible();
}
