# EXAMIS — Performance test plan and results

## 1. Acceptance criteria (initial, adjustable after a baseline — never to make a failing run "pass")

| Metric                                   | Target                                                                                                 |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Public pages p95                         | ≤ 1 s                                                                                                  |
| Authenticated reads p95                  | ≤ 1 s (excluding client network)                                                                       |
| Writes p95 (save, upload ≤ 1 MB, submit) | ≤ 2 s                                                                                                  |
| Unexpected HTTP errors                   | < 1% in steady load                                                                                    |
| Memory                                   | no unbounded growth of PHP-FPM, worker or PostgreSQL RSS during soak                                   |
| Queues                                   | no sustained growth of `pending_jobs` / `oldest_pending_seconds`                                       |
| Integrity                                | zero duplicate references, zero duplicate applications per user and period, after `submission-flow.js` |
| Connections                              | no "too many connections" or pool exhaustion                                                           |

**1000 VUs is not 1000 humans.** Each VU loops a journey with think time (`sleep`). Report both VUs and the achieved requests per second, and relate them to the expected human load (`OPERATIONS.md` §3).

## 2. Procedure

1. Isolated staging stack, sized like production. Record CPU, RAM, disk, network, PostgreSQL version and settings, PHP-FPM settings, and data volume.
2. `php artisan migrate:fresh --seed` then `LOAD_TEST_USERS=1000 php artisan db:seed --class=LoadTestSeeder`. Set `EXAMIS_LOGIN_ATTEMPTS_PER_IP_PER_HOUR=100000` **on staging only** (k6 runs from one IP).
3. Run, stopping at the first failing stage:
    1. `k6 run tests/load/smoke.js`
    2. `k6 run -e MAX_VUS=25 tests/load/normal-load.js` → then 100 → 250 → 500 → 1000
    3. `k6 run -e VUS=200 tests/load/submission-flow.js` + the integrity SQL in the script header + `php artisan examis:verify-integrity`
    4. `k6 run tests/load/spike.js`
    5. `k6 run -e SOAK_DURATION=2h tests/load/soak.js`
    6. `k6 run tests/load/stress.js` (find the breaking point)
4. During each run, sample every minute: node CPU/RAM, PHP-FPM active/idle processes, `pg_stat_activity` counts and locks, queue metrics (Admin → Operations), Redis memory.
5. Fill in §3 and attach the k6 summary (`--summary-export=results/<run>.json`).

Never run these scripts against production. `tests/load/lib/common.js` refuses production-looking hosts unless `I_HAVE_WRITTEN_APPROVAL=yes`.

## 3. Results

Environment for all runs below: **single developer workstation** (Windows 10, Laravel Herd: nginx + **5 php-cgi workers**, PostgreSQL 18.2 with default settings: `shared_buffers=128MB`, `max_connections=100`). k6 v2.3.0 (official binary, checksum verified) ran **on the same machine**. Isolated database `examis_load` with 1000 synthetic accounts. Production-like app mode (`APP_DEBUG=false`, config/route/view/event cache, OPcache). CPU was at 90–100% during load, shared between k6, PHP, PostgreSQL and nginx.

| Date       | Scenario                                             | Peak VUs reached            | Req/s           | p50    | p95                           | Errors | Integrity                                                      | Notes                                         |
| ---------- | ---------------------------------------------------- | --------------------------- | --------------- | ------ | ----------------------------- | ------ | -------------------------------------------------------------- | --------------------------------------------- |
| 2026-10-14 | `smoke.js`                                           | 2                           | 1.9             | 217 ms | read 421 ms / write 883 ms    | 0%     | all 14 checks pass incl. "resubmit returns the same reference" | baseline                                      |
| 2026-10-14 | `normal-load.js` (25→100→250, 30 s ramp / 90 s hold) | **26** (aborted in stage 1) | 6.5             | 2.55 s | read 4.89 s / write 5.36 s    | 0.49%  | 304/304 checks pass                                            | thresholds breached → run stopped as designed |
| 2026-10-14 | same, after fix below (`DB_PERSISTENT=true`)         | **26** (aborted in stage 1) | **13.6** (2.1×) | 865 ms | read **2.1 s** / write 3.76 s | 0.83%  | 609/616 checks (6 login + 1 home timeouts under saturation)    | still above target on this hardware           |

**Finding and fix:** a new PostgreSQL connection costs 60–95 ms on Windows (one OS process per backend plus SCRAM). Each request opened one, so the session lookup alone dominated latency. Measured in-process: `/login` 204 ms → 75 ms with persistent connections. Added the opt-in `DB_PERSISTENT` setting (`config/database.php`). Production should use PgBouncer instead (`OPERATIONS.md`).

**Verdict:** the targets (p95 read ≤ 1 s, write ≤ 2 s) are **not met on this workstation** beyond ~25 concurrent virtual users. The bottleneck is the workstation, not a data or locking problem: CPU is saturated, Herd has 5 PHP workers, and the load generator competes for the same CPU. No integrity errors occurred: the concurrency proof in `docs/database/SCHEMA.md` (120 simultaneous submissions) found zero duplicate references. **The 1000-user target remains unverified** and must be tested on a staging stack sized per `OPERATIONS.md` §3 (2× 4 vCPU app nodes with PHP-FPM, a separate PostgreSQL host, and k6 on a separate load generator).

Raw outputs: `storage/load-results/*.json|*.log|*.csv`.

## 4. Optimizations already in place (design, not yet measured)

- Indexed queries for every list and filter (`docs/database/SCHEMA.md` §3); offset pagination (25/page) for admin lists, cursor pagination for the audit log.
- Grouped count queries for dashboards (one query, not one per status).
- Eager loading everywhere; lazy loading throws outside production (N+1 guard).
- Cached reference data (1 h, keys per type, not per user).
- Translations sent once per locale (Inertia once-prop), not on every navigation.
- Streamed, chunked CSV export (`lazyById(500)`): constant memory.
- Heavy work off the request path: document scanning and notifications are queued.
- Receipts generated on demand (~40–60 MB peak). If receipt downloads spike at deadlines, cache the PDF per (application, status, locale) in private storage.
