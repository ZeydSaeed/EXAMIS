# Synthetic data strategy

## Records, sessions and concurrent users are different numbers

| Quantity | Meaning here | Set by |
| --- | --- | --- |
| Student records | rows in `users` (+ applications) | `QE_STUDENTS` for `PerfSeeder`: 100, 1,000, 5,000, 50,000 |
| Accounts in use | accounts a run signs in with | `QE_STUDENT_OFFSET` + VU, `QE_SUBMIT_OFFSET` + iteration |
| Live sessions | rows in `sessions` during a run | one per signed-in VU (cookies kept across iterations) |
| Concurrent users (VUs) | simulated people acting at once, with think time | `QE_MAX_VUS`, stage targets |
| Requests per second | measured, not assumed | k6 `http_reqs` rate |

100 active students with 10–20 s think time make roughly 5–10 requests per second; 5,000 records do not mean 5,000 concurrent users.

## `PerfSeeder` (VERIFIED by `LoadJourneyContractTest`)

- Calls `SyntheticTarget::assert()` before anything else.
- Seeds reference data, document types, content pages, message templates and `DemoSeeder` (the open period `EX2027` requiring only `personal_photo`; demo staff exist only in this database).
- Students `n = 1…QE_STUDENTS` (max 50,000), inserted in chunks of 1,000 with `insertOrIgnore` (re-running adds what is missing):

| Field | Value | Why |
| --- | --- | --- |
| `public_id` | `00000000-0000-4000-8000-<n:12>` | QR link k6 can compute |
| `access_code` | `PERF` + `n:6`, stored `PERFd-ddddd` (plain text, as the application stores codes) | k6 can compute; accepted by `checkAccessCode` |
| `student_number` | `900000 + n` | within the database check (10000–999999); visible on the printed page |
| `phone` | `0000<n:8>` | no real Iraqi number starts `0000`; integrity SQL counts any other |
| `username`, `email`, `password` | null | as real students (ADR-0010) |

- Staff, only when the process variable `QE_STAFF_PASSWORD` (≥ 12 characters) is given: `perf.reviewer.1–5@qe.localhost`, `perf.supervisor.1–2@qe.localhost`, created without factories (the server has no dev packages).

## Reuse and reset

One-time accounts cannot be reused after submission: they stay on the printed page. A k6 step then fails with "account already submitted" instead of passing. Between runs either move the offsets or rebuild `examis_perf` (`migrate:fresh` + `PerfSeeder`), only on the perf database and only with approval.

## Volumes and when to use them

| Volume | Purpose |
| --- | --- |
| 100 | smoke, functional, submission-flow up to 20 VUs |
| 1,000 | load up to 200 VUs with fresh submissions |
| 5,000 | the expected season (about 5,000 students over two months) |
| 50,000 | query plans and list pages at 10× the season; seed time and disk to be measured first |

Applications at volume (submitted, under review) for the staff list tests are created by running `submission-flow.js`, so they follow the real path; a bulk generator for 50,000 applications is not built yet (MISSING, optional).
