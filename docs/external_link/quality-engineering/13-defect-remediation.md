# Defect remediation and regression

Discover → Baseline → Test → Detect → Reproduce → Diagnose → Root cause → Propose fix → **Approval** → Apply → Regression → Compare → Report.

## Defect record (GitHub issue or `defects/QE-NNN.md`)

| Field | Content |
| --- | --- |
| ID | `QE-NNN` |
| Title, severity | S1 data loss/security, S2 wrong result, S3 SLO breach, S4 cosmetic |
| Run | `QE_RUN_ID`, commit, script, stage |
| Reproduce | exact command with variables (no secrets), seed size, account numbers |
| Evidence | k6 step and reason, `request_id`, Laravel log lines, Grafana window, `pg_stat_statements` top entries, integrity SQL output |
| Root cause | the component or query, with proof |
| Proposed fix | diff, risk, what it must not weaken |
| Tests | the failing test first, related functional and security tests, integrity SQL |
| Before / after | same script, same data volume, same server state |
| Decision | approved by, date |

## Permissions

| Action | Allowed |
| --- | --- |
| Discover, analyse, report | yes |
| Fix the test suite or its documents after proving the defect is in the test | yes, small and separate |
| Propose application changes | yes |
| Apply application code changes | owner's approval |
| Migrations, indexes, PostgreSQL settings | explicit approval |
| Permissions, application logic, RLS | explicit approval + security review |
| Server, services, firewall | explicit approval |
| Delete test data | only after confirming it is synthetic and in `examis_perf` |

Never: weaken validation, permissions, transactions or constraints for speed; touch `sis`; edit real data.

## After a fix

1. The test that found the defect. 2. Related functional tests. 3. Related security and permission tests. 4. The comparative performance run. 5. The integrity SQL. At most **5 fix cycles** per defect, then a report of what remains and a decision from the owner.

## Stop conditions (watchdog and operator)

Stop immediately when: a guard fails; the target is not allowed; non-synthetic data appears (integrity SQL); host CPU > 90% for 2 min or k6 at its CPU quota (result invalid); available RAM < 10% or sustained swapping; disk > 85%; 5xx > 5%; any integrity violation; lock waits > 30 s or a transaction older than 60 s; the public staging host's `/up` slower than 2 s; it is unclear whether the application or the generator is slow; a deployment starts.

## Findings from the first perf runs (2026-11-06)

| ID | Severity | Where | Finding | Status |
| --- | --- | --- | --- | --- |
| QE-001 | test defect | `tests/load/lib/journeys.js` | Smoke ran three people in one k6 cookie jar. The next student's QR link landed on the previous student's printed page, and the reviewer followed a remembered staff redirect (`url.intended`). 3 of 20 steps failed while HTTP errors were 0%, so the strict checks caught it. | FIXED in `e858c02` (`freshDevice()`): smoke-02 passes 34/34 |
| QE-002 | S2 privacy | `EnsureAccountIsActive` before `StudentLoginController::qr` | On a shared device, a student who submitted but did not finish printing stays signed in. If the next student opens **their own** QR link there, they see the previous student's printed page and form. `qr()` tries to sign the other student out, but the middleware redirects first. Reproduce: student A submits; in the same browser, open `/s/<B>`. | FIXED (owner approved 2026-11-06): `EnsureAccountIsActive` lets another student's `student.qr` through, so the controller signs the waiting student out. Regression test in `OneTimeAccountTest`, failing before the fix and passing after. Full suite 538/538 on PostgreSQL. Not deployed yet. |
| QE-003 | S3 performance | `ApplicationSubmissionController::submit` → `ReceiptRenderer::warm` | Submit renders the PDF inside the request: average 0.89 s, maximum 1.49 s, 40% host CPU at 5 concurrent submits. Every other request is under 75 ms. Two simultaneous submits can both miss the cache and render twice. At deadline peaks, submit will be the first bottleneck. | OPEN: measure at 20 VUs first. Candidate fixes (approval needed): render on the queue after commit, or lock the warm per application. |
