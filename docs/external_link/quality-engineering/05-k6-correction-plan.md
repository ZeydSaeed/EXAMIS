# k6 correction (done in this phase)

## Why six scripts were incompatible

The scripts were written on 2026-10-14 for the first version, where students had a username and a password and the dashboard listed periods and applications. Since then ADR-0009 (identifier + trusted device or access code), ADR-0010 (one-time accounts, phone registration, printed page) and the five-step form changed every student step. The old helpers did not fail loudly; several **reported success while doing nothing**:

| Old behaviour | Why it gave a false or wrong result |
| --- | --- |
| `login()`: `GET/POST /login` with `username` + `password` on the students host | that route belongs to the staff host only; students have no password. The `logged in` check failed, but later steps still ran |
| `applicantReads()`: `GET /dashboard`, `GET /notifications` → "200" | both are now redirects (dashboard → next step; notifications → dashboard). k6 follows redirects, so the checks passed on some other page |
| `submissionJourney()` read `props.openPeriods` / `props.applications` from the dashboard | those props no longer exist; the function found no period and **returned silently**: a "complete" journey with no write |
| `PUT /applications/:id` with `full_name_ar`, `surname`, … and no `step` | fields were split and steps added; Laravel answers a failed form with a redirect back, so `status === 200` passed **with validation errors** |
| Uploaded 6 PDFs + photo | the period requires only `personal_photo` |
| Read `reference_number` from the page after submitting | the printed page does not carry it; the idempotency check could not see anything |
| `POST /logout` | the student route is `/student/logout`; after submitting, every route redirects to the printed page |
| `LoadTestSeeder`: `loadtest.NNNN` with password | students have no username or password; no QR id, no student number, no access code: no account could sign in |
| Guard: deny-list `/examis\.(gov|edu)|production/` + bypass `I_HAVE_WRITTEN_APPROVAL=yes` | did not match `students.mahani-iq.com` or the staging hosts; a single variable bypassed it |
| `stress.js` 1,500 VUs, `spike.js` 1,000, `soak.js` 230, hard-coded | far above what a shared 4 vCPU server can generate and serve; no ceiling |

## Per script

| Script | Old flow | Current flow | Changes | Proof of correctness | Status |
| --- | --- | --- | --- | --- | --- |
| `smoke.js` | public pages + broken submission + dashboard reads | preflight; public pages; one full student journey; security probes (IDOR, staff portal, signed-out, reviewer limits); supervisor journey | rebuilt on the new libs; functional thresholds (every step must pass) | self-test 51/51; contract test; `k6 inspect` | REBUILT, not run over HTTP |
| `normal-load.js` | 25→1000 VUs, username login | stages 5→10→20→50→100→150→200 cut by `QE_MAX_VUS`; 55% public, 45% students filling (sign in once, read back, real save), 1 supervisor, fresh submissions at a fixed rate | new mix, VU ceiling, `noCookiesReset`, SLO thresholds with abort | same | REBUILT, not run |
| `submission-flow.js` | 100 VUs, broken login, silent no-op | each VU: whole journey; the submission sent twice at the same moment (`http.batch`); then the integrity SQL | racing submit, strict checks, DB verification | same + SQL tested in Pest | REBUILT, not run |
| `registration-journey.js` | already current (ADR-0010) | unchanged path; now through the guard, synthetic `0000…` phones, strict page checks | guard, checks | same | REBUILT, not run (ran once 2026-11-04 on public staging, which is no longer allowed) |
| `spike.js` | 50→1000 VUs, broken reads | 5 → `QE_MAX_VUS` in 30 s; public + filling students | per-run approval, ceiling, abort at 5% errors | `k6 inspect` refuses without approval | REBUILT, needs approval |
| `stress.js` | 600→1500 VUs | quarters of `QE_MAX_VUS` (≤ 200) | per-run approval, ceiling | same | REBUILT, needs approval |
| `soak.js` | 150+80 VUs + submissions, 2 h | `QE_SOAK_VUS` (default 50), 1 h default, 1 submission/min | per-run approval, ceiling | same | REBUILT, needs approval |

## What a step must prove now

`lib/common.js#evaluatePage`: the response is 200, carries an Inertia page, the page is the one expected (a redirect to the terms, code or staff sign-in page fails the step), it has no validation errors, and the step's own condition holds:

| Step | Must hold |
| --- | --- |
| sign-in | lands on specialization, the draft, or the printed page; never the code page |
| specialization | draft created, status `draft`, branch stored |
| each form step | `lock_version` + 1 (the save was applied), the next step opened, every value sent reads back |
| repeated save (load loop) | a changed value, so the save is a real write; then read-back of everything saved |
| photo | listed, `verified`, nothing missing |
| submit (also racing) | this student's printed page (student number matches) |
| PDF | 200, `application/pdf`, body starts `%PDF`, ≥ 1000 bytes |
| refusals | exactly 404 (foreign application) or 403 (reviewer on staff management); the staff host shows its sign-in to a student |

A failed step records `qe_step_failures{step}`, logs `{qe_run, step, reason, vu, iteration, status, url, request_id}` and stops that journey. Thresholds: functional scripts require `qe_step_ok == 1`, `checks == 1`, `http_req_failed == 0`.

The database decides what HTTP cannot: `sql/post-run-integrity.sql` (duplicate references, duplicate applications, submissions without a reference or with ≠ 1 submission event, period counter, duplicate current documents, submissions without a document, non-synthetic applicants). Every count must be 0.

## Proof that no false success is possible (offline)

`selftest.js`, 51 checks, 0 HTTP requests, all PASS (`evidence/k6-selftest.txt`): the guard refuses 20 bad targets and accepts the right one; the checks fail on the code page, the terms page (expired session), HTTP 500/419/429, a page without Inertia data, a transport error, validation errors, an unchanged lock version, the wrong step, a value that reads back different, a missing or rejected photo, another student's printed page, a form returned instead of the printed page, a served page where 404/403 is required, 403 where 404 is required, and an HTML or empty "PDF"; each with a positive control.

## What is still open

- The scripts have not run over HTTP against `0f0de40`: needs the isolated environment (gates G2–G4).
- Per-address rate limits: all VUs come from 127.0.0.1; k6 sends `X-QE-Client-IP` that only the loopback perf vhost may trust (Nginx `realip`, server change, REQUIRES APPROVAL). Without it, sign-ins above 10/min fail with clear 429s, never a false pass.
- Staff race (two supervisors on one application) is covered by Pest (`tests/Concurrency`), not by k6.
- Registration capacity cannot be load-tested without changing the application's limits (3/min, 30/day per address); out of scope.
