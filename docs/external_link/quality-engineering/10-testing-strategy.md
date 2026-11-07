# Testing strategy

| Layer                    | Tool                                                                                                                                                                        | Where                                         | Status                                    |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- | ----------------------------------------- |
| A. Functional            | Pest (537 tests), the QE contract test                                                                                                                                      | workstation, CI                               | VERIFIED (SQLite); PostgreSQL run pending |
| B. Browser               | Playwright: Chromium, mobile Chromium (Pixel 7); add a tablet project (iPad) and a staff-session project; RTL checks exist in `public-portal.spec.ts`, `responsive.spec.ts` | workstation or server, against the perf vhost | EXISTS BUT NOT VERIFIED; tablet MISSING   |
| C. Concurrency           | Pest `tests/Concurrency` (real PostgreSQL connections), k6 `submission-flow.js` (racing double submit), DB integrity SQL                                                    | perf                                          | rebuilt, not run                          |
| D. Load                  | k6 `normal-load.js`                                                                                                                                                         | perf                                          | rebuilt, not run                          |
| E. Stress / spike / soak | k6, per-run approval                                                                                                                                                        | perf                                          | designed, not run                         |
| F. Security              | see `12-security-testing.md`                                                                                                                                                | CI + perf                                     | partly existing                           |

## A. Functional

Registration, sign-in by QR and code, sign-out, draft saving with stale-version refusal, photo upload and rejection of bad files, submission and its idempotency, tracking, staff review and every status transition, reports and PDF: covered by existing Pest tests plus the contract test. Gaps: mail transport failure (R6), network loss during submission (G1 of the old status report).

## C. Concurrency scenarios

| Scenario                              | Test                                                                                                | Status                |
| ------------------------------------- | --------------------------------------------------------------------------------------------------- | --------------------- |
| many students saving at once          | `normal-load.js` filling students                                                                   | rebuilt               |
| one student, two sessions, stale save | `ApplicationDraftTest::rejects a save based on a stale version`                                     | existing              |
| submit several times, at once         | `submission-flow.js` (`http.batch` of two) + integrity SQL                                          | rebuilt               |
| two staff on one application          | `tests/Concurrency/StaffRaceTest` (admin race); review race: MISSING in k6, add to Pest concurrency | partly                |
| reference counter contention          | 120 simultaneous submissions proof (2026-10-14, `SCHEMA.md`); `submission-flow.js` at 20+ VUs       | historical, to repeat |
| retries after a timeout               | retry step in every submission journey                                                              | rebuilt               |

## D. Load stages

| Stage    | VUs            | `QE_MAX_VUS`           | Go on when                       |
| -------- | -------------- | ---------------------- | -------------------------------- |
| Smoke    | 1 (`smoke.js`) | 1                      | every step passes, integrity 0   |
| Small    | 5 then 10      | 8 / 13                 | SLOs hold, generator below quota |
| Medium   | 20             | 23                     | same                             |
| Target 1 | 50             | 53                     | same, RAM headroom ≥ 25%         |
| Target 2 | 100            | 103                    | same                             |
| Extended | 150–200        | 153 / 203 → capped 200 | owner approves after Target 2    |

`normal-load.js` adds 3 helper VUs (1 supervisor, up to 2 submitting), hence the ceilings above.

## E. Stress, spike, soak (approval per run)

| Test   | Shape                                                  | Stops when                               |
| ------ | ------------------------------------------------------ | ---------------------------------------- |
| Spike  | 5 → max in 30 s, hold 2 min, back to 5, 3 min recovery | errors > 5%, watchdog                    |
| Stress | quarter steps to max (≤ 200)                           | errors > 5%, watchdog                    |
| Soak   | 50 VUs (default) for 1 h, 1 submission/min             | SLO abort, memory growth trend, watchdog |
