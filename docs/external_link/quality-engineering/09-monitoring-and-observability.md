# Monitoring and observability (designed, REQUIRES APPROVAL to install)

All free, all local, bound to 127.0.0.1, viewed through an SSH tunnel; nothing is sent to an outside service.

| Source            | Tool                                                                                            | Metrics                                                                                                                                      |
| ----------------- | ----------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| Host              | `node_exporter` (+ `vmstat`/`iostat`/`pidstat` in a pinch)                                      | CPU incl. steal, RAM, swap, disk space, disk I/O, network, load average                                                                      |
| Per process group | `node_exporter` systemd collector or `process-exporter` (optional)                              | CPU and RSS of k6, Prometheus/Grafana, PostgreSQL, PHP-FPM, Redis, queue workers                                                             |
| PHP-FPM           | `php-fpm_exporter` (needs `pm.status_path` in the perf pool)                                    | active/idle workers, listen queue, max children reached, slow requests                                                                       |
| Nginx             | access log with `$request_time` and `$upstream_response_time`; `stub_status`                    | requests, 4xx/5xx, latency by route                                                                                                          |
| PostgreSQL        | `postgres_exporter` with a read-only monitoring role; `pg_stat_statements` on the perf database | connections and saturation, slow queries, lock waits, deadlocks, long transactions, database size, reads/writes, cache hit ratio, temp files |
| Redis / queue     | `redis_exporter`; SQL on `jobs`, `failed_jobs`                                                  | memory, clients, evictions, errors, queue length, oldest job age, failed jobs                                                                |
| k6                | `--out experimental-prometheus-rw` to the local Prometheus, `--summary-export` JSON             | VUs, RPS, p50/p90/p95/p99 by `type` and `name`, failures, 4xx/5xx/429, dropped iterations, `qe_step_ok`, `qe_step_failures{step}`            |
| Application       | `X-Request-Id` on every response, logged and stored on audit rows                               | links a failed k6 step (it logs `request_id`) to the Laravel log line                                                                        |

## Telling the application apart from the generator

Every report shows, for the same time window: k6 CPU against its quota, total CPU and steal, PHP-FPM listen queue, PostgreSQL lock waits. A run is **invalid** (not a failure of the application) when k6 sat at its CPU quota, or steal exceeded 20%, for more than a minute. Latency is attributed to the application only when the generator had headroom and FPM or PostgreSQL show the wait.

## Dashboards (Grafana, provisioned from files in the repo later)

Host · PHP-FPM and Nginx · PostgreSQL · Redis and queue · k6 run (with run id variable) · Run comparison (same panels for two run ids side by side).

## Run comparison

Each run stores `results/<run-id>/summary.json`, `integrity.txt`, `resources.csv` (1-minute samples) and the commit. `17-execution-log.md` keeps one line per run; the comparison table lists p50/p95/p99 by step, RPS, error rate, peak CPU/RAM, and integrity counts for the previous and the current run.

## Budget

Prometheus 150–300 MB RAM, 1–3 GB disk at 15-day retention; Grafana 100–200 MB; exporters about 60 MB together. Option: run them only during test sessions.
