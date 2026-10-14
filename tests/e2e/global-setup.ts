import { execFileSync } from 'node:child_process';
import { existsSync, rmSync, writeFileSync } from 'node:fs';
import { e2eEnv } from '../../playwright.config';

/**
 * Rebuilds the isolated E2E database from scratch before every run, so
 * tests never depend on leftovers from a previous run.
 */
export default function globalSetup(): void {
    if (existsSync('public/hot')) {
        throw new Error(
            'public/hot exists: stop `npm run dev` and run `npm run build` before E2E tests.',
        );
    }

    if (!existsSync('public/build/manifest.json')) {
        throw new Error('Built assets missing: run `npm run build` first.');
    }

    const database = e2eEnv.DB_DATABASE;
    rmSync(database, { force: true });
    writeFileSync(database, '');

    execFileSync(
        'php',
        [
            'artisan',
            'migrate:fresh',
            '--seed',
            '--seeder=Database\\Seeders\\EndToEndSeeder',
            '--force',
            '--no-interaction',
        ],
        {
            env: { ...process.env, ...e2eEnv },
            stdio: 'inherit',
        },
    );
}
