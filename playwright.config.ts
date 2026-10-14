import { createHash } from 'node:crypto';
import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests run against an isolated SQLite database
 * (database/e2e.sqlite) served by PHP's built-in server, never against the
 * development or production database. Assets must be built first
 * (`npm run build`). Set E2E_ALL_BROWSERS=1 to add Firefox and WebKit.
 */
export const e2ePort = Number(process.env.E2E_PORT ?? 8765);
const port = e2ePort;
export const baseURL = `http://127.0.0.1:${port}`;

/** Test-only key, deterministic so the server and setup agree. Not a secret. */
const appKey =
    'base64:' + createHash('sha256').update('examis-e2e-only').digest('base64');

export const e2eEnv: Record<string, string> = {
    APP_ENV: 'e2e',
    APP_DEBUG: 'false',
    APP_KEY: appKey,
    APP_URL: baseURL,
    APP_LOCALE: 'ar',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.resolve('database/e2e.sqlite'),
    DB_URL: '',
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    DOCUMENTS_DISK: 'documents',
    DOCUMENTS_ROOT: path.resolve('storage/e2e/documents'),
    EXAMIS_REQUIRE_STAFF_2FA: 'false',
    LOG_CHANNEL: 'single',
};

const allBrowsers = process.env.E2E_ALL_BROWSERS === '1';

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './storage/e2e/results',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'storage/e2e/report', open: 'never' }],
    ],
    globalSetup: './tests/e2e/global-setup.ts',
    globalTeardown: './tests/e2e/global-teardown.ts',
    use: {
        baseURL,
        locale: 'ar-IQ',
        testIdAttribute: 'data-test',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        {
            name: 'mobile-chromium',
            use: { ...devices['Pixel 7'] },
            testMatch: /responsive\.spec\.ts/,
        },
        ...(allBrowsers
            ? [
                  { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
                  { name: 'webkit', use: { ...devices['Desktop Safari'] } },
              ]
            : []),
    ],
    webServer: {
        // Laravel's router script resolves the public directory from the CWD.
        command: `php -S 127.0.0.1:${port} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: 'public',
        url: `${baseURL}/up`,
        env: e2eEnv,
        reuseExistingServer: false,
        timeout: 60_000,
        stdout: 'ignore',
        stderr: 'ignore',
    },
});
