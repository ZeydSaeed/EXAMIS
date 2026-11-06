# Test inventory (`0f0de40` + this phase)

| Suite | Size | Last run | Result | Status |
| --- | --- | --- | --- | --- |
| Pest, full (`php artisan test`, SQLite in memory) | 537 tests | 2026-11-06 18:07, workstation | 528 passed, 9 skipped (PostgreSQL-only), 0 failed | VERIFIED: `evidence/pest-full-suite.txt` |
| Pest `tests/Feature/QualityEngineering` (new) | 34 tests, 355 assertions | same | 34 passed | VERIFIED: `evidence/pest-quality-engineering.txt` |
| Pest on PostgreSQL (`composer test:pgsql`) | same suite + concurrency | not run in this phase | — | EXISTS BUT NOT VERIFIED (needs `examis_testing`; the new QE tests skip there until the database carries the synthetic marker) |
| `tests/Concurrency` (real PostgreSQL connections) | `StaffRaceTest` | not run | — | EXISTS BUT NOT VERIFIED |
| Playwright `tests/e2e` | 8 specs, about 30 tests (Chromium, mobile Chromium) | not run in this phase | — | EXISTS BUT NOT VERIFIED; uses PostgreSQL `examis_testing` |
| CI (`.github/workflows/tests.yml`) | quality, Pest on PG18, Playwright, gitleaks | history not readable (`gh` not signed in) | — | UNKNOWN; the `e2e` job lacks PostgreSQL (see `01-…` table) |
| k6 self-test (new, offline) | 51 checks, 0 HTTP | 2026-11-06 | 51/51 | VERIFIED: `evidence/k6-selftest.txt` |
| k6 guard (new) | 7 scripts × refused / allowed / heavy approval | 2026-11-06 | as expected | VERIFIED: `evidence/k6-guard-inspect.txt` |
| k6 scripts over HTTP | 7 scripts | never on `0f0de40` | — | not run: needs an approved environment |
| Larastan level 7 | new seeder files | 2026-11-06 | 0 errors | VERIFIED (console) |
| Pint, `vp check` (oxlint, oxfmt) | new PHP and JS files | 2026-11-06 | pass | VERIFIED (console) |
| Semgrep, ZAP, testssl, axe | — | never | — | MISSING |

## What each new test proves

| Test | Proves |
| --- | --- |
| `SyntheticTargetTest` (26 cases) | the data guard refuses `sis`, staging, development and production names, remote database servers, public portal hosts, production/staging environments, a wrong app, a missing marker, missing or contradictory settings; it stops `PerfSeeder` before any write |
| `LoadJourneyContractTest::seeds synthetic students…` | `PerfSeeder` accounts sign in by QR link + access code under the current auth model |
| `…refuses another student's access code` | one student's code does not open another account |
| `…the k6 journey completes…` | the k6 steps and answers (`fixtures/application.json`) pass validation: specialization → 3 steps (each value read back) → photo verified → submit → printed page → retry harmless (same reference) → real PDF; integrity SQL all 0 |
| `…synthetic staff follow the k6 staff steps` | staff sign-in, queue, open, start review, sign out; reviewer gets 403 on staff management |
| `…cannot open another student's application or the staff portal` | IDOR answers 404; a student session on the staff host lands on the staff sign-in |
| `…a save moves the lock version only when something changed` | the assumption behind the k6 read/write loop |
| `…public pages, sign-out…` | page components and `X-Request-Id` the k6 preflight relies on |
| `…integrity checks see a planted inconsistency` | the post-run SQL detects a counter mismatch and a non-synthetic applicant |
