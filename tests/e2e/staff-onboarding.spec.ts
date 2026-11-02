import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { staffURL } from '../../playwright.config';
import { artisan, invitationLinkFrom, staffPassword } from './helpers';

const PASSWORD = 'Str0ng-Passw0rd!';

async function acceptInvitation(
    page: Page,
    link: string,
    email: string,
): Promise<void> {
    await page.goto(link);

    // The token leaves the address bar before anything else happens.
    await expect(page.getByText(email)).toBeVisible();
    expect(page.url()).not.toContain('#');

    await page.locator('#password').fill(PASSWORD);
    await page.locator('#password_confirmation').fill(PASSWORD);
    await page.getByTestId('accept-invitation-button').click();
    await expect(page).toHaveURL(/\/staff\/login/);
}

test('an administrator activates and invites staff without any shared secret', async ({
    browser,
    page,
}) => {
    test.setTimeout(120_000);

    const stamp = Date.now();
    const adminEmail = `primary.${stamp}@example.com`;
    const reviewerEmail = `reviewer.${stamp}@example.com`;

    // The first administrator comes from the command line.
    const link = invitationLinkFrom(
        artisan('examis:create-admin', 'مدير أساسي', adminEmail),
    );

    await acceptInvitation(page, link, adminEmail);

    // Used once, a link is dead.
    await page.goto(link);
    await expect(page.getByRole('alert')).toBeVisible();

    // Email and password are all it takes.
    await staffPassword(page, adminEmail, PASSWORD);
    await expect(page).toHaveURL(/\/admin\/home/);
    await expect(page.getByRole('link', { name: 'لوحة الإدارة' })).toBeVisible();

    // The administrator adds a staff member, name, email and role, and gets a link once.
    await page.goto(`${staffURL}/admin/users`);
    await page.getByRole('button', { name: 'إضافة موظف' }).click();
    await page.locator('#new-name').fill('مدقق جديد');
    await page.locator('#new-email').fill(reviewerEmail);
    await page.locator('#new-phone').fill('07701234567');
    await page.locator('#new-role-reviewer').click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'إضافة موظف' })
        .click();

    const reviewerLink = await page.getByTestId('invitation-url').inputValue();
    expect(reviewerLink).toContain('/staff/invitation#');

    await page.goto(`${staffURL}/admin/users`);
    await expect(page.getByText('بانتظار التفعيل')).toBeVisible();

    // The new staff member does their part in their own browser, and their
    // role decides what opens.
    const reviewerPage = await (await browser.newContext()).newPage();
    await acceptInvitation(reviewerPage, reviewerLink, reviewerEmail);
    await staffPassword(reviewerPage, reviewerEmail, PASSWORD);
    await expect(reviewerPage).toHaveURL(/\/admin\/home/);
    expect((await reviewerPage.goto(`${staffURL}/admin/users`))?.status()).toBe(
        403,
    );
});

test('a student account cannot sign in on the staff portal', async ({
    page,
}) => {
    await staffPassword(page, 'family@examis.test');

    await expect(page).toHaveURL(/\/staff\/login/);
    await expect(
        page.getByText('البريد الإلكتروني أو كلمة المرور غير صحيحة.'),
    ).toBeVisible();
});
