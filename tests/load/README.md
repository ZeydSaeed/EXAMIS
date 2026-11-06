# external_link k6 suite

Load, concurrency and smoke tests for the student and staff portals, rebuilt on 2026-11-06 against release `0f0de40`. Design and evidence: `docs/external_link/quality-engineering/` (start with `05-k6-correction-plan.md`).

**Nothing here may run against production, the public staging portals (`*.mahani-iq.com`) or any database other than an approved synthetic one.** The guard (`lib/guard.js`) refuses those before k6 sends a request, and every run needs the owner's approval (`15-implementation-roadmap.md`).

## Files

| File                      | What                                                                                                                       |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `lib/guard.js`            | Target allow-list: loopback portal names only, run id, `QE_ENV`, VU ceiling (≤ 200). Runs in the init stage.               |
| `lib/common.js`           | Strict step checks (`evaluatePage`), metrics `qe_step_ok`, `qe_step_failures`, `qe_journey_complete`, thresholds, preflight |
| `lib/student.js`          | QR + access code sign-in, specialization, three form steps, photo, submit, printed page, PDF, refusals                      |
| `lib/staff.js`            | Staff sign-in, queue, search, open, start review, reviewer limits                                                          |
| `lib/journeys.js`         | Whole journeys built from the steps                                                                                        |
| `lib/heavy.js`            | Spike, stress and soak refuse to start without `QE_HEAVY_APPROVAL` equal to `QE_RUN_ID`                                     |
| `fixtures/application.json` | The form answers, shared with the Pest contract test                                                                     |
| `sql/post-run-integrity.sql` | Database checks after a run; every count must be 0                                                                      |
| `selftest.js`             | Offline proof that the guard refuses and that checks cannot pass falsely (no HTTP)                                         |

| Script                    | Kind                       | Approval            |
| ------------------------- | -------------------------- | ------------------- |
| `smoke.js`                | functional, 1 VU           | per session         |
| `submission-flow.js`      | functional under concurrency | per session       |
| `registration-journey.js` | registration once + browsing | per session       |
| `normal-load.js`          | staged load 5 → `QE_MAX_VUS` | per session       |
| `spike.js`, `stress.js`, `soak.js` | heavy             | per run (`QE_HEAVY_APPROVAL`) |

## Order (stop at the first failure)

1. `k6 run ... selftest.js` (offline).
2. `php artisan test --compact tests/Feature/QualityEngineering`: the same journey through Laravel, with the same answers.
3. On the approved test environment only: rebuild the synthetic database, then `smoke.js`, then `post-run-integrity.sql`.
4. `submission-flow.js` at 5, then 20 VUs, then the integrity SQL.
5. `normal-load.js` with `QE_MAX_VUS` raised one stage at a time.

## Variables

| Variable | Meaning |
| --- | --- |
| `QE_ENV` | `local` or `perf` |
| `QE_RUN_ID` | e.g. `2026-11-07-smoke-01`; tags every request and names the results |
| `STUDENTS_URL`, `STAFF_URL` | loopback portals, e.g. `https://students.perf.localhost:8443` |
| `QE_MAX_VUS` | ceiling for the run, at most 200 |
| `QE_STAFF_PASSWORD` | the synthetic staff password given to `PerfSeeder`; never written to reports |
| `QE_STUDENT_OFFSET`, `QE_SUBMIT_OFFSET` | first account numbers (submitted accounts cannot be reused) |
| `QE_HEAVY_APPROVAL` | equal to `QE_RUN_ID` after the owner approved a heavy run |

Synthetic accounts come from `php artisan db:seed --class=PerfSeeder` (process variables `QE_STUDENTS`, `QE_STAFF_PASSWORD`), which refuses any database `SyntheticTarget` does not allow. Student `n` uses QR `/s/00000000-0000-4000-8000-<n:12>`, code `PERF` + `n:6`, student number `900000+n`, phone `0000<n:8>`.

## Reading a result

A run is valid only if `qe_step_ok` and the integrity SQL pass **and** the load generator was not saturated (k6 process CPU below its quota, see `09-monitoring-and-observability.md`). On the single server, k6, monitoring and the application share the CPU: report results as "application + generator on one 4 vCPU host", never as the application's capacity alone.
