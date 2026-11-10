# Test safety guards

Allow-lists only. Every rule must hold, and a failing rule stops the run **before** anything is written or sent.

## Layer 1: data guard, `Database\Seeders\Support\SyntheticTarget` (VERIFIED by `SyntheticTargetTest`, 26 cases)

Called first by `PerfSeeder` (and so by `LoadTestSeeder`).

| Rule                                                       | Allowed                                                                                                                                               |
| ---------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Environment                                                | `testing`, `local`, `perf`, and not production                                                                                                        |
| Application                                                | `APP_NAME = EXAMIS`                                                                                                                                   |
| Portal hosts (`EXAMIS_STUDENTS_HOST`, `EXAMIS_STAFF_HOST`) | `localhost`, `127.0.0.1`, `*.localhost`, `*.test`; anything reachable from the internet is refused                                                    |
| Connection:database                                        | `sqlite::memory:`, `pgsql:examis_testing`, `pgsql:examis_perf` (so `sis`, `examis_staging`, `EXAMIS`, `examis` are refused)                           |
| Database server                                            | `127.0.0.1`, `localhost`, `::1`                                                                                                                       |
| Marker                                                     | for PostgreSQL, `COMMENT ON DATABASE … IS 'external_link:synthetic'`, read back from `pg_database` through the same connection the seeder writes with |

Limits: no code can prove a database holds no real person. The marker is that promise: it is written only on an empty database created for synthetic data. `post-run-integrity.sql` adds a check that every applicant phone starts `0000` (no real Iraqi number does). `examis_testing` has no marker yet, so the new tests skip on PostgreSQL until the owner approves adding it (decision D4).

## Layer 2: k6 target guard, `tests/load/lib/guard.js` (VERIFIED by `selftest.js` and `k6 inspect`)

| Rule                             | Allowed                                                                                                                           |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `QE_ENV`                         | `local`, `perf`                                                                                                                   |
| `QE_RUN_ID`                      | 6–64 of `A-Z a-z 0-9 . _ -`                                                                                                       |
| `STUDENTS_URL`, `STAFF_URL`      | `scheme://host[:port]` only (no path, credentials, query); host `localhost`, `127.0.0.1` or `*.localhost`; the two hosts differ   |
| Name resolution                  | k6 `hosts` pins every `*.localhost` name to 127.0.0.1, so DNS cannot redirect the traffic                                         |
| `QE_MAX_VUS`                     | 1–200; scripts refuse a VU count above it                                                                                         |
| Old variables                    | `BASE_URL`, `I_HAVE_WRITTEN_APPROVAL` are refused                                                                                 |
| Heavy scripts                    | `QE_HEAVY_APPROVAL` must equal `QE_RUN_ID`                                                                                        |
| Identity (setup, first requests) | the students host answers `apply/terms` and the staff host `auth/staff-login`, both with `X-Request-Id`; otherwise the run aborts |

Evidence: all 7 scripts exit 107 with "QE guard refused" for `students.mahani-iq.com` and `students-staging.mahani-iq.com`; the 3 heavy scripts exit 107 without approval (`evidence/k6-guard-inspect.txt`).

## Layer 3: network and server (designed, REQUIRES APPROVAL)

- The perf vhost listens on `127.0.0.1:8443` only, names `students.perf.localhost` / `staff.perf.localhost`; no DNS record, not behind Cloudflare.
- Its own Linux user, PHP-FPM pool, `.env` (600), PostgreSQL database `examis_perf` with its own roles that cannot connect to `examis_staging`, its own Redis database or instance, its own storage directory.
- The perf vhost alone trusts `X-QE-Client-IP` from 127.0.0.1 (Nginx `realip`), so rate limits apply per synthetic client; the public vhost never does.
- `MAIL_MAILER=log`, no SMS provider exists, WhatsApp messages are sent by hand: no message can leave the server.

## Layer 4: run control (designed, REQUIRES APPROVAL)

| Control                    | How                                                                                          |
| -------------------------- | -------------------------------------------------------------------------------------------- |
| One heavy run at a time    | `flock /run/qe/heavy.lock` around k6, ZAP and Playwright                                     |
| No run during a deployment | `release.sh` holds a lock the runner checks (small change to `release.sh`, approval)         |
| Kill switch                | file `/srv/qe/STOP` checked before start and every 15 s by the watchdog                      |
| Resource ceilings          | `systemd-run --scope -p CPUQuota=100% -p MemoryMax=1500M` for k6; similar for ZAP/Playwright |
| Watchdog stop conditions   | `13-defect-remediation.md` §Stop                                                             |

## Layer 5: people

The owner approves each environment change and each test session; heavy runs per run. `17-execution-log.md` records who approved what and when.
