# EXAMIS perf environment (load tests on the staging host)

An isolated copy of the approved staging release, used only for k6 runs. It lives on the same server as staging (`examis-staging-1`), costs nothing extra, and is reachable only on `127.0.0.1:8443`. Design and decisions: `docs/external_link/quality-engineering/08-single-server-architecture.md`.

| Part | Perf | Staging (unchanged) |
| --- | --- | --- |
| Names | `students.perf.localhost`, `staff.perf.localhost` on 127.0.0.1:8443 | public portals via Cloudflare |
| Code | copy of `/srv/examis/current` + `database/seeders`, `tests/load` from the given commit | `/srv/examis/current` |
| PHP-FPM | own master `examis-perf-fpm`, static 16, user `examisperf` | `php8.4-fpm`, pool `examis` |
| PostgreSQL | cluster `16/perf` on 127.0.0.1:5433, `examis_perf` (marked `external_link:synthetic`), `pg_stat_statements` | cluster `16/main`, `examis_staging` |
| Redis | `examis-perf-redis` on 127.0.0.1:6380, no persistence | `redis-server` on 6379 |
| Client address | `X-QE-Client-IP` from k6 (loopback vhost only) | Cloudflare `CF-Connecting-IP` |
| Running | only during a session (`examis-perf.target`, not at boot) | always |

The settings that differ from staging: PostgreSQL `shared_buffers` is 512 MB rather than 1 GB, `max_connections` is 40 rather than 50, and only perf loads `pg_stat_statements`. Every result is recorded with these differences.

## Install (root, once)

```bash
bash provision-perf.sh
```

## Each session (root)

```bash
# On the PC, from a committed tree:
#   git archive --format=tar.gz -o examis-perf-overlay-<sha>.tgz HEAD database/seeders tests/load
bash release-perf.sh /tmp/examis-perf-overlay-<sha>.tgz <sha> 1000   # rebuilds examis_perf, starts the session
examis-qe-run smoke.js <run-id>
examis-qe-run submission-flow.js <run-id> QE_VUS=5 QE_STUDENT_OFFSET=100
systemctl stop examis-perf.target                                    # end of session
```

Rules:
- One heavy tool at a time.
- `examis-qe-run` caps k6 at 1 vCPU and 1.5 GB.
- It samples every component's CPU and memory into `resources.csv`.
- It stops k6 if available memory falls below 10%.
- Results are "application + generator on one 4 vCPU host", never the application's capacity alone.
