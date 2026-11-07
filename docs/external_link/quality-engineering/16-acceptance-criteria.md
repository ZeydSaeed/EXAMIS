# Acceptance criteria

PASS means: the test ran, on a recorded commit and environment, and its output is saved under `evidence/` or `results/<run-id>/`. Anything else is NOT RUN.

| Gate              | Accepted when                                                                                                                                                                                             |
| ----------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Baseline          | commit recorded; owner confirmed it (D1)                                                                                                                                                                  |
| Suite correctness | `selftest.js` all checks pass with 0 HTTP requests; contract test passes on the baseline                                                                                                                  |
| Guard             | every refusal case passes (`SyntheticTargetTest`, `selftest.js`, `k6 inspect` for all scripts); on the server, a negative run against `examis_staging` and the public hosts is refused before any request |
| Environment       | perf vhost reachable only on 127.0.0.1; `examis_perf` carries the marker; its roles cannot connect to `examis_staging`; mail is `log`                                                                     |
| Data              | `PerfSeeder` at the chosen volume; integrity SQL all 0; seed time and disk recorded                                                                                                                       |
| Smoke             | `smoke.js`: `qe_step_ok` = 1, `http_req_failed` = 0, integrity 0                                                                                                                                          |
| Concurrency       | `submission-flow.js` at 5 and 20 VUs: all steps pass; integrity 0 (no duplicate reference, one submission event per application)                                                                          |
| Each load stage   | SLOs in `11-…` hold; integrity 0; k6 below its CPU quota; steal < 20%; RAM headroom ≥ 10%                                                                                                                 |
| Target 100        | the 100-VU stage accepted as above, reported as "on one shared 4 vCPU host with the generator"                                                                                                            |
| Security          | static and passive clean or decided; active scan only after G6; no open high/critical without the owner's decision                                                                                        |
| Remediation       | each defect closed with before/after evidence or escalated after 5 cycles                                                                                                                                 |
