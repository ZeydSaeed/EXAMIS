# external_link quality engineering: executive summary

Date: 2026-11-06. Baseline under test: `0f0de40` (see `01-approved-version-baseline.md`).

Status labels used in this folder: **VERIFIED** (checked, evidence named), **EXISTS BUT NOT VERIFIED**, **MISSING**, **OUTDATED**, **UNKNOWN**, **BLOCKED**, **REQUIRES APPROVAL**. PASS is used only for a test that ran, with its output saved under `evidence/`.

## Decisions by the owner (binding)

- The project is `external_link` (EXAMIS). SIS is unrelated and must never be touched.
- Not launched. All students, staff, applications and files are test data.
- Everything runs on the one existing server; no second server, no load generator, no purchase. Free and open-source tools only.
- Tests target the latest approved version, proven from records, not assumed.

## What was done in this phase

| Item | Result | Evidence |
| --- | --- | --- |
| Approved version | `0f0de40`, the last release recorded as approved and deployed; equal to `origin` on both working branches | `01-approved-version-baseline.md` |
| Student and staff flows | Re-read from the code of `0f0de40`, route by route | `03-student-and-staff-flows.md` |
| k6 suite | 6 of 7 scripts were incompatible with the current sign-in and form; all 7 rebuilt with strict checks | `05-k6-correction-plan.md` |
| False-success proof | 51/51 offline checks PASS, 0 HTTP requests | `evidence/k6-selftest.txt` |
| Guard | Central allow-list guard for data (`SyntheticTarget`) and for k6 targets (`lib/guard.js`); all 7 scripts refuse production and staging hosts before any request | `evidence/k6-guard-inspect.txt`, `evidence/pest-quality-engineering.txt` |
| Synthetic data | `PerfSeeder` (100 to 50,000 students, staff) matching the current auth model | `07-synthetic-data-strategy.md` |
| Contract test | The k6 journey replayed through Laravel with the same accounts and answers: 34/34 PASS | `evidence/pest-quality-engineering.txt` |
| Regression | Full Pest suite 537 tests on SQLite: 528 PASS, 9 skipped (PostgreSQL-only), 0 failed; on PostgreSQL `examis_testing` (marked synthetic): 537/537 PASS, 2,293 assertions | `evidence/pest-full-suite.txt`, `evidence/pest-full-suite-pgsql.txt` |

## What was not done (and why)

- No k6 run over the network, no load, no tool installed, no database created on the server: each needs a separate approval (`15-implementation-roadmap.md`).
- Server resources were measured read-only at gate G1 (2026-11-06 21:49): 4 vCPU idle, 6.9 GB RAM and 67 GB disk free, 17 of 50 PostgreSQL connections in use, no QE tools installed, no `sis` database (`08-single-server-architecture.md`, `evidence/g1-server-inspection.txt`).
- Security phase (2026-11-07): Semgrep, dependency audits, Playwright 47/47 and ZAP passive + active scans on perf. No High-risk alert. Found and fixed: QE-005 (password prompt before the permission check), QE-006 (NUL byte → 500), QE-007 (non-UUID key → 500 on PostgreSQL), QE-008 (Symfony advisories). The rescan of the fixes shows 0 × 500. Not yet on staging. Prometheus/Grafana: not installed (cgroup sampling is enough so far).

## Answer in one line

The suite proves what it claims, cannot reach a forbidden target, and has run on an isolated synthetic copy on the staging server up to 200 concurrent users, a 30-second surge and a 2-hour soak with 0 errors and 0 integrity violations; the security scans found no high-risk issue, and the four defects they found are fixed and verified on perf, waiting for release to staging.

## Next gate

G1 to G7 are done (2026-11-06/07). The perf copy runs on the server (`deploy/perf/`). Load up to 100 users PASSES: p95 39 ms, host CPU 27.5%, integrity 0. QE-002 (shared-device privacy) and QE-003 (PDF inside submit: 3.0 s → 0.26 s) are fixed and released to staging as `b9a9096`. G7 heavy runs on `b9a9096` PASS: stress to 200 VUs (p95 ≈ 48 ms, host CPU 53%, no breaking point below the 200 cap), spike 5 → 200 in 30 s (0 errors, recovered within a minute), soak 2 h with 121 submissions (no latency drift, memory flat at the end), integrity 0 on every run. All figures are "application + generator on one shared 4 vCPU host". Security phase done (`13-defect-remediation.md` QE-005..010). Next: the owner approves releasing the fixes to staging, and decides on QE-009 (dev tooling upgrades) and QE-010 (Nginx `nosniff`, `/up`). See `15-implementation-roadmap.md`.
