import { expect, test } from "@playwright/test";
import {
    BIRTH_DATE,
    applicant,
    completeApplicationForm,
    login,
    logout,
    pngFile,
    uniqueUsername,
    uploadDocument,
} from "./helpers";

test.describe("applicant journey", () => {
    test("registers a new account and lands on the dashboard with the username shown", async ({
        page,
    }) => {
        await page.goto("/register");
        await page.locator("#name").fill("طالب جديد");
        await page.locator("#username").fill(uniqueUsername("e2e.newcomer"));
        await page.locator("#email").fill("e2e-family@examis.test");
        await page.locator("#password").fill("password");
        await page.locator("#password_confirmation").fill("password");
        await page.locator("#terms").check();
        await page.getByTestId("register-user-button").click();

        await expect(page).toHaveURL(/\/dashboard/);
        await expect(
            page.getByText(uniqueUsername("e2e.newcomer")),
        ).toBeVisible();
    });

    test("rejects a wrong password with a generic message", async ({
        page,
    }) => {
        await page.goto("/login");
        await page.locator("#username").fill(applicant(1));
        await page.locator("#password").fill("not-the-password");
        await page.getByTestId("login-button").click();

        await expect(
            page.getByText("اسم المستخدم أو كلمة المرور غير صحيحة."),
        ).toBeVisible();
    });

    test("fills, saves, resumes and submits an application, then downloads the receipt", async ({
        page,
    }) => {
        await login(page, applicant(1));
        await expect(page.locator("html")).toHaveAttribute("dir", "rtl");

        await page.getByTestId("start-application").first().click();
        await expect(page).toHaveURL(/\/applications\/[0-9a-f-]+\/edit/);

        // Server-side validation in Arabic.
        await page.locator("#student_name").fill("Sara");
        await page.getByTestId("next-step").click();
        await expect(
            page.getByText("يجب كتابة اسم الطالب بالأحرف العربية فقط."),
        ).toBeVisible();

        // Draft survives a reload because it lives on the server.
        await page.locator("#student_name").fill("سارة");
        await page.getByTestId("save-draft").click();
        await expect(page.getByText("تم حفظ المسودة.")).toBeVisible();
        await page.reload();
        await expect(page.locator("#student_name")).toHaveValue("سارة");

        await completeApplicationForm(page);

        await page.locator("#declaration").check();
        // A double click must not submit twice: the server is idempotent and the button disables.
        await page.getByTestId("submit-application").dblclick();
        await expect(page.getByTestId("submission-confirmed")).toBeVisible();

        const reference =
            (await page.title()).match(/EX\d{4}-\d{6}[0-9X]/)?.[0] ?? "";
        expect(reference).toMatch(/^EX2027-\d{6}[0-9X]$/);

        const receiptHref = await page
            .getByTestId("download-receipt")
            .getAttribute("href");
        const receipt = await page.request.get(receiptHref ?? "");
        expect(receipt.status()).toBe(200);
        expect(receipt.headers()["content-type"]).toContain("application/pdf");
        expect((await receipt.body()).subarray(0, 5).toString()).toBe("%PDF-");

        // Public tracking with reference + birth date.
        await logout(page);
        await page.goto("/track");
        await page.locator("#reference_number").fill(reference);
        await page.locator("#birth_date").fill(BIRTH_DATE);
        await page.getByTestId("track-button").click();
        await expect(page.getByTestId("track-result")).toContainText(reference);
    });

    test("blocks a file type that the document does not allow", async ({
        page,
    }) => {
        await login(page, applicant(2));
        await page.getByTestId("start-application").first().click();
        await page.getByRole("button", { name: /المستمسكات/ }).click();

        await page.getByTestId("file-personal_photo").setInputFiles({
            name: "notes.txt",
            mimeType: "text/plain",
            buffer: Buffer.from("hello"),
        });
        await expect(
            page.getByText("نوع الملف غير مسموح لهذا المستند."),
        ).toBeVisible();

        await uploadDocument(page, "personal_photo", pngFile("portrait.png"));
        await expect(page.getByTestId("document-personal_photo")).toContainText(
            "مقبول",
        );
    });

    test("shows what is still missing on the review step", async ({ page }) => {
        await login(page, applicant(3));
        await page.getByTestId("start-application").first().click();
        await page.getByRole("button", { name: /المراجعة والإرسال/ }).click();

        await expect(page.getByTestId("missing-requirements")).toBeVisible();
        await expect(page.getByTestId("submit-application")).toBeDisabled();
    });
});
