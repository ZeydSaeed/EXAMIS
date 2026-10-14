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

| Date | Environment (CPU/RAM/DB) | Scenario | Peak VUs | Req/s | p50 | p95 | p99 | Errors | Notes                                                                                                                                                  |
| ---- | ------------------------ | -------- | -------- | ----- | --- | --- | --- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| —    | —                        | —        | —        | —     | —   | —   | —   | —      | **Not executed**: k6 is not installed in this environment and no staging stack exists (Blocked B6). The scripts are written but have not yet been run. |

## 4. Optimizations already in place (design, not yet measured)

- Indexed queries for every list and filter (`docs/database/SCHEMA.md` §3); offset pagination (25/page) for admin lists, cursor pagination for the audit log.
- Grouped count queries for dashboards (one query, not one per status).
- Eager loading everywhere; lazy loading throws outside production (N+1 guard).
- Cached reference data (1 h, keys per type, not per user).
- Translations sent once per locale (Inertia once-prop), not on every navigation.
- Streamed, chunked CSV export (`lazyById(500)`): constant memory.
- Heavy work off the request path: document scanning and notifications are queued.
- Receipts generated on demand (~40–60 MB peak). If receipt downloads spike at deadlines, cache the PDF per (application, status, locale) in private storage.
