# Test inventory (`0f0de40` + this phase)

| Suite | Size | Last run | Result | Status |
| --- | --- | --- | --- | --- |
| Pest, full (`php artisan test`, SQLite in memory) | 537 tests | 2026-11-06 18:07, workstation | 528 passed, 9 skipped (PostgreSQL-only), 0 failed | VERIFIED: `evidence/pest-full-suite.txt` |
| Pest `tests/Feature/QualityEngineering` (new) | 34 tests, 355 assertions | same | 34 passed | VERIFIED: `evidence/pest-quality-engineering.txt` |
| Pest on PostgreSQL (`composer test:pgsql`) | 537 tests incl. concurrency and the QE self-target test | 2026-11-06 21:50, workstation, PostgreSQL `examis_testing` (marked synthetic, D4) | 537 passed, 2,293 assertions, 0 skipped (≈ 133 s) | VERIFIED: `evidence/pest-full-suite-pgsql.txt` |
| `tests/Concurrency` (real PostgreSQL connections) | `StaffRaceTest` (4 tests) | 2026-11-06 21:55, PostgreSQL `examis_testing` | 4 passed, 7 assertions | VERIFIED: `evidence/pest-concurrency-pgsql.txt` |
| CI (`.github/workflows/tests.yml`) | quality, Pest on PG18, Playwright, gitleaks | history not readable (`gh` not signed in) | — | UNKNOWN; the `e2e` job now gets a PostgreSQL 18 service like the `pgsql` job (2026-11-06), not yet run in CI |
| k6 self-test (new, offline) | 51 checks, 0 HTTP | 2026-11-06 | 51/51 | VERIFIED: `evidence/k6-selftest.txt` |
| k6 guard (new) | 7 scripts × refused / allowed / heavy approval | 2026-11-06 | as expected | VERIFIED: `evidence/k6-guard-inspect.txt` |
| k6 `smoke.js` on perf | 1 VU, 4 journeys | 2026-11-06 20:13 UTC, perf copy of `0f0de40` | 34/34 steps, 0 HTTP errors, integrity 0 | PASS: `evidence/perf/2026-11-06-smoke-02/` |
| k6 `submission-flow.js` on perf | 5 VUs, racing submit | 2026-11-06 20:14 UTC | 55/55 steps, 0 HTTP errors, integrity 0 | PASS: `evidence/perf/2026-11-06-submit-05/` |
| k6 `submission-flow.js` on perf | 20 VUs, racing submit | 2026-11-06 20:55 UTC | 220/220 steps, 0 HTTP errors, integrity 0; p95 3.41 s (QE-003) | PASS (functional): `evidence/perf/2026-11-06-submit-20b/` |
| k6 `normal-load.js` on perf | 5→10→20→50 VUs, 16.5 min | 2026-11-06 21:00 UTC | 6,506/6,506 steps, 0/7,437 HTTP errors, p95 40 ms, integrity 0 | PASS: `evidence/perf/2026-11-06-normal-50b/` |
| k6 `normal-load.js` on perf, release `b9a9096` | 5→100 VUs, 20.6 min | 2026-11-06 21:38 UTC | 13,857/13,857 steps, 0/15,535 HTTP errors, p95 39 ms, integrity 0 | PASS: `evidence/perf/2026-11-07-normal-100/` |
| k6 `stress.js` on perf, release `b9a9096` | 50→100→150→200 VUs, 22 min | 2026-11-07 09:41 UTC | 45,106/45,106 steps, 0/49,573 HTTP errors, p95 47 ms (≈ 48 ms at 200), host CPU 53% at 200, integrity 0 | PASS: `evidence/perf/2026-11-07-stress-200/` |
| k6 `spike.js` on perf, release `b9a9096` | 5 → 200 VUs in 30 s, hold 2 min, recovery | 2026-11-07 10:04 UTC | 12,088/12,088 steps, 0/13,098 HTTP errors, p95 75 ms, recovered within 1 min, integrity 0 | PASS: `evidence/perf/2026-11-07-spike-200/` |
| k6 `soak.js` on perf, release `b9a9096` | 50 VUs + 1 submission/min, 2 h | 2026-11-07 10:12 UTC | 122,180/122,180 steps, 0/130,709 HTTP errors, p95 39 ms with no drift, 121 submitted, FPM memory +28 MB then flat, integrity 0 | PASS: `evidence/perf/2026-11-07-soak-2h/` |
| k6 `registration-journey` | — | not run | — | NOT RUN |
| Larastan level 7 | new seeder files | 2026-11-06 | 0 errors | VERIFIED (console) |
| Pint, `vp check` (oxlint, oxfmt) | new PHP and JS files | 2026-11-06 | pass | VERIFIED (console) |
| Semgrep CE 1.184 (`p/php`, `p/javascript`, `p/typescript`, `p/react`, `p/secrets`, `p/owasp-top-ten`; metrics off) | 147 rules, 383 files | 2026-11-07, workstation | 5 findings, all reviewed as not defects (`13-…`) | VERIFIED: `evidence/security/semgrep-2026-11-07.json` |
| `composer audit`, `npm audit` | locked dependencies | 2026-11-07, workstation | Symfony advisories fixed (QE-008); dev tooling deferred (QE-009) | VERIFIED: `evidence/security/*-audit-2026-11-07.txt` |
| Playwright `tests/e2e` (Chromium + mobile Chromium) | 47 tests | 2026-11-07, workstation, `examis_testing` | first run 45/47 (QE-005 + a spec defect); after the fixes **47/47**, and 47/47 again after QE-006/007 | PASS: `evidence/playwright-2026-11-07-*.txt` |
| ZAP 2.17.0 passive scan on perf (`b9a9096`) | guest, student and administrator identities; 44 seeded requests + spider | 2026-11-07 13:41 UTC | no High; Medium/Low header findings only (QE-010 or by design) | DONE: `evidence/perf/2026-11-07-zap-passive/` |
| ZAP 2.17.0 active scan on perf (`b9a9096`) | same identities, 22,649 requests, 12.7 min | 2026-11-07 13:45 UTC | no injection, XSS, traversal or command alerts; 27 × 500 found (QE-006, QE-007); integrity 0 | DONE: `evidence/perf/2026-11-07-zap-active/` |
| ZAP active scan on perf, candidate `692b206` (fixes) | same plan, 23,847 requests | 2026-11-07 14:15 UTC | **0 × 500**, 0 application errors, integrity 0; same header alerts | PASS: `evidence/perf/2026-11-07-zap-active-after/`, `evidence/perf/zap-before-after.txt` |
| Pest on PostgreSQL after the fixes | 548 tests | 2026-11-07 | 548/548, Larastan 0 errors | VERIFIED: `evidence/security/pest-pgsql-after-qe-007-2026-11-07.txt` |
| testssl, axe | — | never | — | MISSING |

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
