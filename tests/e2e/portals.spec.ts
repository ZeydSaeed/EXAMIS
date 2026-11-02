import { expect, test } from '@playwright/test';
import { staffURL, studentsURL } from '../../playwright.config';
import { registerStudent, staffLogin } from './helpers';

test.describe('two portals', () => {
    for (const path of [
        '/admin',
        '/admin/users',
        '/staff/login',
        '/staff/invitation',
        '/settings/security',
    ]) {
        test(`${path} does not exist on the student portal`, async ({
            page,
        }) => {
            const response = await page.goto(`${studentsURL}${path}`);

            expect(response?.status()).toBe(404);
        });
    }

    for (const path of ['/apply/terms', '/register', '/portal', '/track']) {
        test(`${path} does not exist on the staff portal`, async ({ page }) => {
            const response = await page.goto(`${staffURL}${path}`);

            expect(response?.status()).toBe(404);
        });
    }

    test('a signed-in student is a stranger to the staff portal', async ({
        context,
        page,
    }) => {
        await registerStudent(page);

        await page.goto(`${staffURL}/admin`);
        await expect(page).toHaveURL(/\/staff\/login/);

        const staffCookies = await context.cookies(staffURL);
        const studentCookies = await context.cookies(studentsURL);

        expect(
            studentCookies.some(
                (cookie) => cookie.name === 'examis_students_session',
            ),
        ).toBe(true);
        expect(
            staffCookies.some(
                (cookie) => cookie.name === 'examis_students_session',
            ),
        ).toBe(false);
    });

    test('a student cookie replayed under the staff name opens nothing', async ({
        context,
        page,
    }) => {
        await registerStudent(page);

        const student = (await context.cookies(studentsURL)).find(
            (cookie) => cookie.name === 'examis_students_session',
        );

        await context.addCookies([
            {
                name: 'examis_staff_session',
                value: student?.value ?? '',
                url: staffURL,
            },
        ]);

        await page.goto(`${staffURL}/admin`);
        await expect(page).toHaveURL(/\/staff\/login/);
    });

    test('signed-in staff are strangers to the student portal', async ({
        context,
        page,
    }) => {
        await staffLogin(page, 'admin');

        const cookie = (await context.cookies(staffURL)).find(
            (item) => item.name === 'examis_staff_session',
        );

        expect(cookie?.httpOnly).toBe(true);
        expect(cookie?.sameSite).toBe('Strict');
        expect(cookie?.domain).toBe(new URL(staffURL).hostname);
        expect(cookie?.expires).toBe(-1);

        await page.goto(`${studentsURL}/dashboard`);
        await expect(page).toHaveURL(/\/apply\/terms/);
    });

    test('the staff portal keeps out of search engines and service workers', async ({
        page,
    }) => {
        const response = await page.goto(`${staffURL}/staff/login`);

        expect(response?.headers()['x-robots-tag']).toContain('noindex');

        const registrations = await page.evaluate(
            async () =>
                (await navigator.serviceWorker?.getRegistrations())?.length ??
                0,
        );

        expect(registrations).toBe(0);
    });

    test('neither portal links to the other', async ({ page }) => {
        await page.goto(`${studentsURL}/portal`);
        await expect(page.locator(`a[href*="staff"]`)).toHaveCount(0);

        await page.goto(`${staffURL}/staff/login`);
        await expect(page.locator(`a[href*="apply"]`)).toHaveCount(0);
    });
});
