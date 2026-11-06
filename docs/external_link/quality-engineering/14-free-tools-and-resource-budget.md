# Free tools and resource budget

Extra monthly cost: **$0**. The server is already paid for; every tool below is free and open source; nothing is bought or subscribed.

## Already available (no install)

| Tool | Where | Status |
| --- | --- | --- |
| Pest, Larastan, Pint | repository (`vendor`) | VERIFIED on the workstation |
| oxlint/oxfmt (`vp check`), `tsc` | repository (`node_modules`) | VERIFIED on the workstation |
| Playwright | repository | EXISTS BUT NOT VERIFIED (browsers not checked) |
| k6 v2.2.0 | workstation only | VERIFIED (workstation) |
| gitleaks, composer/npm audit | CI | EXISTS, CI history UNKNOWN |
| PostgreSQL views (`pg_stat_activity`, `pg_locks`), `EXPLAIN`, slow-query log, `log_lock_waits` | server | VERIFIED (configuration files) |
| `examis:verify-integrity`, `/health/ready`, `X-Request-Id` | application | VERIFIED (code) |
| Installed on the server | — | UNKNOWN until gate G1 |

## Needing installation on the server (REQUIRES APPROVAL, after G1 measures resources)

| Tool | RAM | CPU | Disk | Runs |
| --- | --- | --- | --- | --- |
| k6 (official arm64 binary, checksum) | 0.3–0.6 GB at 100 VUs, ~1 GB at 200 | 0.5–1 vCPU, capped | ~60 MB + results | session only |
| Prometheus | 150–300 MB | low | 1–3 GB (15 days) | session or always |
| Grafana OSS | 100–200 MB | low | ~300 MB | session or always |
| node, postgres, redis, php-fpm exporters | ~60 MB together | negligible | small | with Prometheus |
| `pg_stat_statements` | small | negligible | — | perf database |
| Perf environment (pool 10–12 workers, database, Redis) | 1.5–2 GB during a session | shares the app CPU | 1–5 GB | session only |
| OWASP ZAP (Java, `-Xmx1g`) | 1–1.5 GB | ~1 vCPU | ~1 GB | session, alone |
| Playwright Chromium | 0.5–1 GB | spikes | ~0.5 GB | session, alone |

Semgrep CE runs in CI or on the workstation, not on the server. No Docker on the server: it would add its own overhead and its published ports bypass `ufw`.

## Memory during a 100-VU session (estimate)

Staging services 3–3.5 GB + perf environment 1.5–2 GB + monitoring 0.5 GB + k6 0.6 GB ≈ **5.6–6.6 GB of 7.5 GB**. That is why ZAP and Playwright never run during a load test, and why the watchdog stops at < 10% available RAM.

## If the server turns out too small

Free alternatives, no purchase: run monitoring only during sessions; stop the public staging workers during a session (the project has no real users yet); lower the perf pool; cap at 50 VUs; run ZAP and Playwright from the owner's PC through an SSH tunnel to the perf vhost.
