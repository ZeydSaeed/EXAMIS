# Implementation roadmap

| Phase | Goal | Done when (evidence) | Status | Approval |
| --- | --- | --- | --- | --- |
| 0 Approved version | prove the baseline | `01-approved-version-baseline.md` | done, confirmed by the owner 2026-11-06 | — |
| 1 Current flows | routes and behaviour of `0f0de40` | `03-…`, contract test PASS | done (staff steps without ★ read from code) | — |
| 2 k6 repair | no false success, current flows | self-test 51/51, guard evidence, contract test | done, committed `b463e83`; not run over HTTP | G2 |
| 3 Safety guard | one guard for data and targets | `SyntheticTargetTest`, guard evidence | done, committed; marker on local `examis_testing` (D4); layers 3–4 designed | server parts G2 |
| 4 Synthetic data | `PerfSeeder` at 100–50,000 | contract test; seed timings on perf | seeder done; volumes not run | G3 |
| 5 Functional and concurrency | on the approved test environment | Pest on PostgreSQL, Playwright, `smoke.js`, `submission-flow.js` + SQL | not started | G3, G4 |
| 6 Monitoring | free, local, capped | dashboards show a smoke run | not started | G2 |
| 7 Gradual load | 5 → 100 (→ 200) | per-stage results + integrity 0 + generator headroom | not started | G5 per session |
| 8 Security | static → passive → active | reports, no open high/critical without a decision | not started | G6 |
| 9 Remediation | limited fix cycles | defects closed with before/after | — | per fix |
| 10 Continuous | periodic, free | 4 weeks of reports | — | after all gates |

## Gates

| Gate | What the owner approves |
| --- | --- |
| ~~G1~~ done 2026-11-06 | Read-only SSH inspection of `examis-staging-1`: CPU, RAM, swap, disk, services, FPM/PostgreSQL settings, database names (names only), installed tools. No change. |
| **G2** (next) | Build the perf environment and install k6 + monitoring per `08-…` and `14-…` (packages, users, vhost on 127.0.0.1, database, Redis, exporters) |
| G3 | Create `examis_perf` with the marker and run `PerfSeeder` there (and the marker on local `examis_testing`, D4) |
| G4 | Run `smoke.js`, then `submission-flow.js` at 5 VUs, then the integrity SQL |
| G5 | Each load session, one stage at a time |
| G6 | ZAP active scan; spike, stress, soak per run |

## Decisions for the owner

| ID | Decision |
| --- | --- |
| D1 | Confirm `0f0de40` as the approved baseline (or name another) |
| D2 | Commit this phase's changes (tests, seeders, docs), on which branch, and how to keep the other session from sweeping them |
| D3 | Gate G1 now? |
| D4 | Mark `examis_testing` as synthetic so the new tests also run on PostgreSQL |
| D5 | `examis_perf` in the existing PostgreSQL cluster or a second cluster |
| D6 | Monitoring always on or only during sessions |
| D7 | Trust `X-QE-Client-IP` in the perf vhost (rate limits per synthetic client) |
| D8 | Allowed slowdown of public staging during sessions, and session times |
| D9 | Update the outdated documents listed in `01-…` (THREAT-MODEL T1/T19, ARCHITECTURE, PERFORMANCE §2, `.ai` guidelines, CI e2e job) |
