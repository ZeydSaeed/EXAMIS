# Approved version baseline

Checked 2026-11-06 17:40–18:10 +0300, read-only Git commands only (no checkout, reset, switch, stash or clean).

## Finding

**Approved baseline: `0f0de40` ("Show the form number and the new wording on the printed page", 2026-11-06 17:25:38 +0300).**

There are no Git tags or release branches. Approval is recorded in the deployment log the owner keeps outside the repository (`C:\Users\TWENTY F C\.examis-staging\status.md`): each release entry is headed `Release <sha> (approved: …)`. The newest entry is `Release 0f0de40 (approved: printed page per the user's image)`, deployed as `releases/20261106142707` with `/up 200`, then pushed.

| Item | Approved version | Current state | Same? | Evidence |
| --- | --- | --- | --- | --- |
| Commit | `0f0de40` | HEAD `0f0de40` | yes | `git rev-parse HEAD`; status.md entry "Release 0f0de40 (approved …)" |
| Branch | released from `perf/phase-a-hardening` and `feature/staff-portal-auth` | working tree on `feature/staff-portal-auth`; both branches at `0f0de40` | yes | `git for-each-ref`; ahead/behind 0/0 |
| Remote | pushed | `origin/feature/staff-portal-auth` = `origin/perf/phase-a-hardening` = `0f0de40` | yes | ahead/behind 0/0 against origin |
| Deployed on staging | `releases/20261106142707` | not checked on the server (no SSH in this phase) | EXISTS BUT NOT VERIFIED | status.md only |
| `main` | — | `6adb973` (2026-10-15), 98+ commits behind | not the approved version | `git log main` |
| Tags | none | none | — | `git tag -l` |
| Local changes before this phase | none | clean at 17:40 | yes | `git status --porcelain` |
| Untracked files before this phase | none | none | yes | same |

The approval text is written by the session that deployed, quoting the owner. That is the strongest record available; it is not a signed release. **The owner should confirm `0f0de40` as the baseline** (decision D1 in `15-implementation-roadmap.md`).

## Concurrent work

Another Claude Code session is active on the same working tree: transcript `038eb0a9…` last written 17:30, and HEAD moved during this phase (`e8e3965` → `23118d5` → `0f0de40`, commits at 16:57 and 17:25, deployed at 17:27). It was not interrupted or inspected further.

Consequence: the baseline can move again. Every test run records the commit it ran on (`QE_RUN_ID` plus `git rev-parse HEAD` in `17-execution-log.md`), and a run on a different commit is a different baseline.

Owner confirmed this baseline on 2026-11-06 (D1). This phase was committed as `b463e83` on `feature/staff-portal-auth`, with `perf/phase-a-hardening` fast-forwarded to match. Neither branch is pushed.

## Documents and tests that do not match `0f0de40`

| Item | Says | Code of `0f0de40` | Status |
| --- | --- | --- | --- |
| `tests/load/*` (6 of 7 scripts) | students sign in at `POST /login` with username and password | QR link + access code, no password (ADR-0009, ADR-0010) | OUTDATED → rebuilt in this phase |
| `database/seeders/LoadTestSeeder.php` | applicants with username and password | students have neither | OUTDATED → now delegates to `PerfSeeder` |
| `docs/testing/PERFORMANCE.md` §2 | `LoadTestSeeder`, `BASE_URL`, `I_HAVE_WRITTEN_APPROVAL` | refused by the new guard | OUTDATED (results history in §3 still valid as history) |
| `docs/security/THREAT-MODEL.md` T19 | tracking needs reference AND birth date | reference only (ADR-0010, `TrackApplicationController`) | UPDATED 2026-11-06 |
| `docs/security/THREAT-MODEL.md` T1 | password policy for all sign-ins | students have no password | UPDATED 2026-11-06 |
| `docs/decisions/0011-separate-staff-portal.md` | staff TOTP always required | removed by ADR-0012 (no 2FA) | superseded, marked "Amended by" |
| `docs/architecture/ARCHITECTURE.md` §2 | "staff password login with 2FA", password reset by username | no 2FA, no public reset | UPDATED 2026-11-06 |
| `docs/architecture/STATUS-REPORT.md` | 2026-10-14 state | many later changes | marked as a historical snapshot 2026-11-06 |
| `.ai/guidelines/examis.md` Testing | E2E uses `database/e2e.sqlite` | `playwright.config.ts` uses PostgreSQL `examis_testing` | UPDATED 2026-11-06 |
| `.github/workflows/tests.yml` job `e2e` | installed `pdo_sqlite` only, no PostgreSQL service | Playwright needs PostgreSQL | FIXED 2026-11-06 (PostgreSQL service + `pdo_pgsql`); not yet run in CI |
| Student notifications (TEST-STRATEGY R6, ARCHITECTURE) | status changes reach students in the portal | notifications are stored, but `KeepStudentsOnTheirPath` sends students away from the notifications page | OUTDATED |

The owner approved the document updates (D9) on 2026-11-06. Student notifications still need a product decision: should students see status changes in the portal at all?
