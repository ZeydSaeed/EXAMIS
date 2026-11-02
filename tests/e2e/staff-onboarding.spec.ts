import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { staffURL } from '../../playwright.config';
import {
    answerChallenge,
    artisan,
    fillOtp,
    invitationLinkFrom,
    nextTotp,
    staffPassword,
    totp,
} from './helpers';

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

/** Password, forced setup, QR key, first code, then the codes once. */
async function enrol(
    page: Page,
    email: string,
): Promise<{ secret: string; recoveryCodes: string[] }> {
    await staffPassword(page, email, PASSWORD);
    await expect(page).toHaveURL(
        /\/user\/confirm-password|\/settings\/security/,
    );

    if (page.url().includes('confirm-password')) {
        await page.locator('#password').fill(PASSWORD);
        await page.getByTestId('confirm-password-button').click();
    }

    await expect(page).toHaveURL(/\/settings\/security/);
    await page.getByRole('button', { name: 'Enable 2FA' }).click();

    await expect(page.locator('svg').first()).toBeVisible();
    const secret = await page.locator('input[readonly]').first().inputValue();
    expect(secret).toMatch(/^[A-Z2-7]{32}$/);

    await page.getByRole('button', { name: 'Continue' }).click();
    await fillOtp(page, totp(secret));
    await page.getByRole('button', { name: 'Confirm' }).click();

    await page.getByTestId('issue-recovery-codes').click();
    const list = page.getByTestId('recovery-codes').getByRole('listitem');
    await expect(list).toHaveCount(8);

    return { secret, recoveryCodes: await list.allTextContents() };
}

test('an administrator activates, enrols, invites and recovers without any shared secret', async ({
    browser,
    page,
}) => {
    test.setTimeout(240_000);

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

    // Nothing in the staff area opens before two-factor is set up.
    const { secret, recoveryCodes } = await enrol(page, adminEmail);

    // The codes are never shown again.
    await page.reload();
    await expect(page.getByTestId('recovery-codes')).toHaveCount(0);
    await expect(page.getByTestId('issue-recovery-codes')).toHaveCount(0);

    // The usual way in: password, then a live code.
    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await staffPassword(page, adminEmail, PASSWORD);
    await expect(page).toHaveURL(/\/two-factor-challenge/);
    await answerChallenge(page, await nextTotp(page, secret));
    await expect(page).toHaveURL(/\/admin\/home/);

    // The administrator adds a staff member, name, email and role, and gets a link once.
    await page.goto(`${staffURL}/admin/users`);
    await page.getByRole('button', { name: 'إضافة موظف' }).click();
    await page.locator('#new-name').fill('مدقق جديد');
    await page.locator('#new-email').fill(reviewerEmail);
    await page.locator('#new-role-reviewer').click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'إضافة موظف' })
        .click();

    const reviewerLink = await page.getByTestId('invitation-url').inputValue();
    expect(reviewerLink).toContain('/staff/invitation#');

    await page.goto(`${staffURL}/admin/users`);
    await expect(page.getByText('بانتظار التفعيل')).toBeVisible();

    // The new staff member does their part in their own browser.
    const reviewerPage = await (await browser.newContext()).newPage();
    await acceptInvitation(reviewerPage, reviewerLink, reviewerEmail);
    await enrol(reviewerPage, reviewerEmail);
    await reviewerPage.goto(`${staffURL}/admin/users`);
    expect((await reviewerPage.goto(`${staffURL}/admin/users`))?.status()).toBe(
        403,
    );

    // A lost phone: one recovery code gets the administrator in, once.
    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await staffPassword(page, adminEmail, PASSWORD);
    await page.getByText('login using a recovery code').click();
    await page.locator('input[name="recovery_code"]').fill(recoveryCodes[0]);
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/\/admin\/home/);

    await page.getByTestId('sidebar-menu-button').click();
    await page.getByTestId('logout-button').click();
    await staffPassword(page, adminEmail, PASSWORD);
    await page.getByText('login using a recovery code').click();
    await page.locator('input[name="recovery_code"]').fill(recoveryCodes[0]);
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/\/two-factor-challenge/);
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
