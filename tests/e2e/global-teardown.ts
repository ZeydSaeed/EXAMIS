import { execFileSync } from 'node:child_process';
import { e2ePort } from '../../playwright.config';

/**
 * Playwright on Windows can leave the `php -S` child running after the run;
 * stop it explicitly so the next run gets a clean server and database.
 */
export default function globalTeardown(): void {
    const marker = `127.0.0.1:${e2ePort}`;

    try {
        if (process.platform === 'win32') {
            execFileSync('powershell', [
                '-NoProfile',
                '-Command',
                `Get-CimInstance Win32_Process -Filter "Name='php.exe'" | Where-Object { $_.CommandLine -match '${marker}' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }`,
            ]);
        } else {
            execFileSync('pkill', ['-f', `php -S ${marker}`]);
        }
    } catch {
        // Nothing left running.
    }
}
