# EXAMIS — Operations guide

## 1. Runtime components

| Component                                                         | Notes                                                                                                                                                                        |
| ----------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Nginx + PHP-FPM 8.4 (OPcache on, `opcache.validate_timestamps=0`) | Stateless app nodes behind a load balancer with WAF and DDoS protection                                                                                                      |
| Queue workers                                                     | `php artisan queue:work --queue=documents,default --tries=3 --max-time=3600 --memory=256` under systemd/supervisor. Separate pools when documents and notifications compete. |
| Scheduler                                                         | One node: `* * * * * php artisan schedule:run` (rescan stuck documents, nightly integrity check, prune resets and failed jobs)                                               |
| PostgreSQL 16+                                                    | See `docs/database/SCHEMA.md` for settings and roles                                                                                                                         |
| Redis 7                                                           | Cache and queue in production (`maxmemory-policy allkeys-lru` for the cache DB; `noeviction` for the queue DB, or separate instances)                                        |
| Object storage                                                    | Private bucket, block public access, versioning plus object lock, server-side encryption                                                                                     |
| Mail/SMS provider                                                 | Through Laravel mail/notification channels; credentials in the secret manager                                                                                                |

## 2. Deployment (zero-downtime release directories)

```bash
# on the build runner
composer install --no-dev --optimize-autoloader --classmap-authoritative
npm ci && npm run build
# on each node, into releases/<sha>, then:
php artisan migrate --force          # migrations are additive and backward compatible with the previous release
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
ln -sfn releases/<sha> current && sudo systemctl reload php8.4-fpm
php artisan queue:restart            # workers pick up new code after their current job
```

**Rollback:** point `current` back at the previous release, reload PHP-FPM, run `queue:restart`. Migrations are not rolled back in production. Write forward fixes instead (`docs/database/SCHEMA.md` §2). A release whose migration is not backward compatible must say so in its notes and needs a maintenance window.

Production `.env` essentials: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `EXAMIS_REQUIRE_STAFF_2FA=true`, `DOCUMENTS_DISK=<private s3 disk>`, `DOCUMENT_SCANNER=<av scanner>`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `LOG_LEVEL=warning`, `LOG_CHANNEL=daily` or `stderr` to a log shipper.

## 3. Capacity planning (initial: 1000 concurrent users — to be validated by k6)

Assumptions, to be replaced by measurements: an average request costs 40–80 ms of CPU and 35–50 MB of PHP-FPM memory, and an applicant session averages one request every 10–20 s. That gives about 50–100 req/s at 1000 active users (spikes 3×).

| Resource               | Starting point                                                                  | Reasoning                                                                                                               |
| ---------------------- | ------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| PHP-FPM workers        | 2 nodes × `pm.max_children = 40` (`pm = static`)                                | 40 × 50 MB ≈ 2 GB RAM per node; at 80 ms/request, 80 workers serve ~1000 req/s CPU-bound, enough headroom for 3× spikes |
| App node size          | 4 vCPU / 8 GB each                                                              | workers + OPcache + OS                                                                                                  |
| Queue workers          | 4 (documents) + 2 (default)                                                     | scan is CPU-light (structural) and AV-heavy (ClamAV ~100–500 ms/file)                                                   |
| PostgreSQL connections | via PgBouncer: pool 50 for 80 FPM workers                                       | not every request holds a connection the whole time                                                                     |
| Upload limits          | `client_max_body_size 6m` (Nginx), `upload_max_filesize=6M`, `post_max_size=8M` | max document type size 5 MB + overhead                                                                                  |
| PDF memory             | mPDF peak ~40–60 MB per receipt                                                 | `memory_limit=256M` is sufficient; receipts are on-demand, not batched                                                  |
| Temp disk              | `/tmp` ≥ 2 GB per node                                                          | concurrent uploads × 5 MB                                                                                               |

More workers do not mean more throughput once CPU or database connections saturate. Tune from the k6 results (`docs/testing/PERFORMANCE.md`).

## 4. Monitoring and alerts

| Signal                  | Source                                                                                 | Alert when                                         |
| ----------------------- | -------------------------------------------------------------------------------------- | -------------------------------------------------- |
| Availability            | external check on `/up` (liveness) and `/health/ready` (readiness: DB, cache, storage) | 2 consecutive failures                             |
| Error rate              | access logs / APM (5xx share)                                                          | > 1% for 5 min                                     |
| Latency                 | access logs / APM p95                                                                  | reads > 1 s or writes > 2 s for 10 min             |
| Queue backlog           | Admin → Operations (`pending_jobs`, `oldest_pending_seconds`) or `queue:monitor`       | oldest job > 5 min                                 |
| Failed jobs             | `failed_jobs` count                                                                    | any increase in 15 min                             |
| Documents awaiting scan | Operations page                                                                        | > 50 for 15 min                                    |
| Auth anomalies          | `audit_events` `auth.failed` / `auth.lockout` per hour (Operations page)               | > 5× baseline                                      |
| Rate-limit hits         | 429s in access logs                                                                    | sustained spike (possible attack or limit too low) |
| CPU / RAM / disk        | node exporter                                                                          | CPU > 85% for 10 min, RAM > 90%, disk > 80%        |
| PostgreSQL              | `pg_stat_activity`, locks, replication lag, connections                                | waiting locks > 30 s, connections > 80% of pool    |
| Backups                 | backup job exit status                                                                 | missed or failed run                               |
| Integrity               | nightly `examis:verify-integrity` exit code                                            | non-zero                                           |
| Restore drills          | drill log                                                                              | older than 35 days                                 |

Logs are structured, and every request carries `X-Request-Id`, which is also stored on audit rows. Never log passwords, OTPs, tokens or document contents. The application does not do this, and log shippers must not add request bodies.

Public endpoints reveal no versions, dependency names or connection details. Details are on the admin Operations page (permission `operations.view`).

## 5. External service outages — expected behavior

| Outage            | Behavior                                                                                                                                                                 |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Mail/SMS provider | Notification jobs retry with backoff (30 s → 30 min, 5 tries), then land in `failed_jobs`; in-app notifications still work; `php artisan queue:retry all` after recovery |
| Redis             | Production cache/queue unavailable → readiness fails, so the LB drains the node. Bring Redis back; no durable data lives only in Redis. Scans are re-queued by schedule. |
| Object storage    | Uploads and downloads fail with an error; drafts and submissions without new uploads continue; readiness reports storage failure                                         |
| PostgreSQL        | Service unavailable (readiness fails); follow `DISASTER-RECOVERY.md`                                                                                                     |
| AV scanner        | Documents stay `pending` (not downloadable, but count toward submission); rescans continue every 10 min                                                                  |
