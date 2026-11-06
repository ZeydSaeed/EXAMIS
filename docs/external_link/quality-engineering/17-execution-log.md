# Execution log

One line per action that ran. Times +0300. No secrets.

| When | Commit | Environment | Action | Result | Evidence |
| --- | --- | --- | --- | --- | --- |
| 2026-11-06 17:40 | `0f0de40` | workstation, read-only Git | baseline check: HEAD, branches, origin, tags, status, deployment log | baseline `0f0de40`; another session active (HEAD moved twice during the check) | `01-approved-version-baseline.md` |
| 2026-11-06 17:50 | `0f0de40` | workstation, `php artisan route:list` (no database) | route inventory | 89 routes (incl. framework and Inertia DevTools) | `03-student-and-staff-flows.md` |
| 2026-11-06 18:07:03 | `0f0de40` + QE changes | workstation, Pest, SQLite `:memory:` | `tests/Feature/QualityEngineering` | 34 passed, 355 assertions | `evidence/pest-quality-engineering.txt` |
| 2026-11-06 18:07:09 | same | same | full Pest suite | 537 tests: 528 passed, 9 skipped, 0 failed (≈ 62 s) | `evidence/pest-full-suite.txt` |
| 2026-11-06 18:08:12 | same | workstation, k6 2.2.0, offline | `selftest.js` | 51/51 checks, 0 HTTP requests | `evidence/k6-selftest.txt` |
| 2026-11-06 18:08 | same | workstation, `k6 inspect` (init only) | guard on all 7 scripts | production and staging hosts refused (exit 107); heavy scripts refused without approval; VU ceiling 500 refused | `evidence/k6-guard-inspect.txt` |
| 2026-11-06 18:05 | same | workstation | Pint, Larastan (new seeders), `vp check` (tests/load) | pass, 0 errors | console |

Not run (at 18:08): anything on the server, any k6 request over the network, Playwright, ZAP, Semgrep, PostgreSQL test run.

## 2026-11-06, after the owner's approval ("موافق": D1, D2, D3, D4, D9)

| When | Commit | Environment | Action | Result | Evidence |
| --- | --- | --- | --- | --- | --- |
| 21:47 | `0f0de40` + QE changes | workstation | QE Pest re-run; k6 self-test (and without settings) | 34/34 passed; 51/51 checks, 0 HTTP; without settings the guard refuses with exit 107 | console |
| 21:48 | `b463e83` | workstation, Git | committed this phase by explicit paths on `feature/staff-portal-auth`; `perf/phase-a-hardening` fast-forwarded to match (as the release log keeps them equal) | not pushed | `git log` |
| 21:49 | — | `examis-staging-1`, SSH as root, read-only script | G1 inspection: CPU, RAM, disk, services, FPM, PostgreSQL settings, database names, tools | idle host; no `sis` database; no QE tools; 17/50 PostgreSQL connections | `evidence/g1-server-inspection.txt` |
| 21:50 | `b463e83` | workstation, PostgreSQL `examis_testing` | marker `external_link:synthetic` set on `examis_testing` (D4); full Pest on PostgreSQL | 537 passed, 2,293 assertions, 0 skipped | `evidence/pest-full-suite-pgsql.txt` |
| 21:55 | same | same | `--testsuite=Concurrency` | 4 passed, 7 assertions | `evidence/pest-concurrency-pgsql.txt` |
| 21:56 | same | workstation | documents updated (D9): THREAT-MODEL T1/T19, ARCHITECTURE §2, STATUS-REPORT banner, `.ai` E2E note; CI `e2e` job gets a PostgreSQL service | CI not run | this commit |
