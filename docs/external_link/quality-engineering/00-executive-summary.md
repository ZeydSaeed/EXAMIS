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
| Regression | Full Pest suite 537 tests: 528 PASS, 9 skipped (PostgreSQL-only), 0 failed | `evidence/pest-full-suite.txt` |

## What was not done (and why)

- No k6 run over the network, no load, no server access, no tool installed, no database created: each needs a separate approval (`15-implementation-roadmap.md`).
- Server resources are **UNKNOWN** today; the last measurement is from 2026-11-04 (`08-single-server-architecture.md`).
- Playwright, ZAP, Semgrep, monitoring: designed, not run.

## Answer in one line

The suite can now prove what it claims, cannot reach a forbidden target, and has accounts that sign in the way students and staff really do; it has not yet been run against a live environment.

## Next gate

Gate G1 (approval): read-only inspection of the server, then G2: build the isolated `perf` environment on it. See `15-implementation-roadmap.md`.
