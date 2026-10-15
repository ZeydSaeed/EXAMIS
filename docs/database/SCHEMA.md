# EXAMIS — Database design (PostgreSQL)

PostgreSQL is the source of truth. All migrations are portable to SQLite for local tests. Guarantees that SQLite cannot express are added **only on PostgreSQL** (marked 🐘) and verified in CI against a PostgreSQL service.

## 1. Entity relationships

```
users 1─* role_assignments
users 1─* applications *─1 exam_periods *─* document_types   (document_type_exam_period: is_required)
applications 1─* application_documents *─1 document_types
applications 1─* application_events
users 1─* notifications (Laravel database notifications)
audit_events (actor_id → users, no FK: history must never cascade)
reference_items (type, code)            content_pages (slug)
```

## 2. Tables and constraints

| Table                       | Key constraints                                                                                                                                                                                                                                                             | Why                                                                                 |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `users`                     | `UNIQUE(username)`; `email` indexed, **not unique**; 🐘 `CHECK account_type IN ('applicant','staff')`; 🐘 `CHECK username ~ '^[a-z0-9._-]{4,32}$'`                                                                                                                          | Shared family email (ADR-0002)                                                      |
| `password_reset_tokens`     | PK on the `email` column, which holds the **username**                                                                                                                                                                                                                      | Tokens never collide between accounts that share an email                           |
| `role_assignments`          | `UNIQUE(user_id, role)`; `role` enum check                                                                                                                                                                                                                                  | No duplicate grants                                                                 |
| `exam_periods`              | `UNIQUE(code)`; status enum; 🐘 `CHECK closes_at > opens_at`; `last_sequence` counter                                                                                                                                                                                       | Per-period numbering under row lock                                                 |
| `document_types`            | `UNIQUE(code)`; 🐘 `CHECK max_size_kb BETWEEN 1 AND 20480`                                                                                                                                                                                                                  |                                                                                     |
| `document_type_exam_period` | `PK(exam_period_id, document_type_id)`                                                                                                                                                                                                                                      |                                                                                     |
| `applications`              | `UNIQUE(uuid)`, `UNIQUE(reference_number)`, `UNIQUE(verification_token)`, **`UNIQUE(user_id, exam_period_id)`**; FKs `RESTRICT`; status enum; 🐘 `CHECK status='draft' OR (reference_number, submitted_at, declaration_accepted_at NOT NULL)`; 🐘 `CHECK lock_version >= 1` | No duplicate applications or numbers; a submitted application always has its number |
| `application_documents`     | `UNIQUE(uuid)`, `UNIQUE(path)`, **partial `UNIQUE(application_id, document_type_id) WHERE replaced_at IS NULL`** (both engines)                                                                                                                                             | Exactly one current document per type; replaced documents keep their history        |
| `application_events`        | FK `RESTRICT`; 🐘 trigger rejects `UPDATE`/`DELETE`                                                                                                                                                                                                                         | Append-only history                                                                 |
| `audit_events`              | 🐘 trigger rejects `UPDATE`/`DELETE`                                                                                                                                                                                                                                        | Tamper resistance inside the application role                                       |
| `reference_items`           | `UNIQUE(type, code)`                                                                                                                                                                                                                                                        |                                                                                     |
| `content_pages`             | `UNIQUE(slug)`                                                                                                                                                                                                                                                              |                                                                                     |

Deletion policy: applicants with applications cannot be deleted (`RESTRICT`). Retention is handled by the archival policy (§5), not by cascades.

## 3. Indexes and the queries they serve

| Index                                                                                                      | Query                                                                                   |
| ---------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| `applications(user_id, exam_period_id)` UNIQUE                                                             | applicant dashboard; "already applied" check                                            |
| `applications(exam_period_id, status, submitted_at)`                                                       | admin queue filtered by period and status, ordered by submission time; report group-bys |
| `applications(assigned_to, status)`                                                                        | "my queue", reviewer visibility scope                                                   |
| `applications(national_id_number)`                                                                         | staff exact search                                                                      |
| `applications(reference_number)` UNIQUE                                                                    | staff exact search, public tracking                                                     |
| `application_documents(application_id, document_type_id)`                                                  | current-document lookups                                                                |
| `application_documents(status, created_at)`                                                                | pending-scan monitoring and sweeps                                                      |
| `application_events(application_id, id)`                                                                   | timeline in insertion order                                                             |
| `audit_events(created_at)`, `(action, created_at)`, `(actor_id, created_at)`, `(subject_type, subject_id)` | audit viewer filters with cursor pagination                                             |
| `exam_periods(status, opens_at)`                                                                           | open-period lookup on the home page                                                     |

Name search uses a left-anchored `LIKE 'term%'`, which can use a B-tree only with `text_pattern_ops`. For production scale, add:

```sql
CREATE INDEX CONCURRENTLY applications_full_name_ar_prefix ON applications (full_name_ar text_pattern_ops);
-- or, for substring search, with the extension approved by the DBA:
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX CONCURRENTLY applications_full_name_ar_trgm ON applications USING gin (full_name_ar gin_trgm_ops);
```

### Measured plans (2026-10-14, PostgreSQL 18.2, workstation, `examis_perf`: 100k applications / 80k submitted, 500k audit events)

`EXPLAIN (ANALYZE, BUFFERS)` of the queries the admin screens issue:

| Query                                           | Before     | Plan before                                                              | After                          | Plan after                                                                |
| ----------------------------------------------- | ---------- | ------------------------------------------------------------------------ | ------------------------------ | ------------------------------------------------------------------------- |
| Queue, period + status filter, page 1           | 26.8 ms    | Bitmap index `(exam_period_id, status, submitted_at)` + top-N sort       | unchanged                      | same                                                                      |
| Paginator count for that filter                 | 2.1 ms     | Index Only Scan                                                          | unchanged                      | same                                                                      |
| **Unfiltered queue (supervisor default)**       | **194 ms** | Parallel Seq Scan + sort (ordering on expression `submitted_at IS NULL`) | **0.29 ms** (page 200: 4.1 ms) | Index Scan `applications_queue_order_index`                               |
| **Reviewer visibility scope**                   | **88 ms**  | BitmapOr + sort of 28k rows                                              | **0.23 ms**                    | Index Scan `applications_queue_order_index`                               |
| **Staff search (reference / ID / name prefix)** | **94 ms**  | Parallel Seq Scan (unindexed `LIKE` branch)                              | **2.6 ms**                     | BitmapOr of three index scans                                             |
| Name prefix search                              | seq scan   | —                                                                        | 1.3 ms                         | `applications_full_name_ar_prefix_index` (`text_pattern_ops`)             |
| Dashboard counts by status                      | 42 ms      | Seq Scan + HashAggregate                                                 | unchanged                      | acceptable at 100k; cache 60 s if it exceeds ~200 ms at production volume |
| Report by governorate, one period               | 22.6 ms    | Bitmap index on period                                                   | unchanged                      | same                                                                      |
| Audit viewer page (cursor)                      | 0.8 ms     | Index Scan Backward on PK                                                | unchanged                      | same                                                                      |

Fixes shipped in migration `2026_10_14_194309_add_query_indexes_to_applications_table`. The queue no longer orders by `submitted_at IS NULL`, which was redundant because the CHECK constraint guarantees `submitted_at` for non-drafts. Reproduce with `docs/database/explain-checks.sql`.

### Concurrency proof (2026-10-14, PostgreSQL 18.2)

120 separate PHP processes started simultaneously, each running `SubmitApplication`: 60 distinct drafts in one period, every draft submitted twice at the same moment (double-click or retry race).
Result: 0 errors, 0 deadlocks; 60 unique references with contiguous sequences 1..60; period counter = 60; exactly one submission event per application; both racing processes of each pair returned the **same** reference.

## 4. Concurrency strategy

- Short transactions. Each write locks exactly one application row (`FOR UPDATE`) and, at submission, the period counter row. Lock order is always application → period, so the two cannot deadlock each other.
- `DB::transaction($callback, 3)` retries on deadlock or serialization failure.
- Recommended role settings (production):

```sql
ALTER ROLE examis_app SET lock_timeout = '5s';
ALTER ROLE examis_app SET statement_timeout = '30s';
ALTER ROLE examis_app SET idle_in_transaction_session_timeout = '60s';
```

- The application role should NOT own the tables (owner = `examis_owner`, used only for migrations). This keeps the append-only triggers out of reach of the runtime role.

## 5. Retention, archival, partitioning

- Draft applications untouched for 12 months after their period closes: candidates for purge (pending a retention decision, Blocked B7).
- `audit_events` / `application_events`: keep online for at least 3 years. Partition by month (`PARTITION BY RANGE (created_at)`) only when tables pass about 50M rows or vacuum and insert latency degrade. Migration path: create a partitioned twin, backfill in batches, swap names in a maintenance window.
- No partitioning today: no data volume justifies it (YAGNI).

## 6. PostgreSQL sizing starting point (8 vCPU / 32 GB, dedicated)

| Setting                                                 | Value                           | Reasoning                                                      |
| ------------------------------------------------------- | ------------------------------- | -------------------------------------------------------------- |
| `max_connections`                                       | 200 (via PgBouncer: pool 40–60) | PHP-FPM workers × nodes must fit the pool, not raw connections |
| `shared_buffers`                                        | 8GB                             | ~25% of RAM                                                    |
| `effective_cache_size`                                  | 24GB                            | ~75% of RAM                                                    |
| `work_mem`                                              | 16MB                            | 60 active queries × a few sorts each stays well under RAM      |
| `maintenance_work_mem`                                  | 1GB                             |                                                                |
| `random_page_cost`                                      | 1.1                             | SSD                                                            |
| `autovacuum_vacuum_scale_factor` (applications, events) | 0.05                            | frequent updates during peak periods                           |

Validate these on the target host with the k6 runs. Do not copy them blindly.

## 7. Row-level security

Not enabled. There is a single authority today, and authorization is enforced in policies and scopes. If the platform becomes multi-authority (per-directorate staff isolation), add `authority_id` to periods and applications and enable RLS with `SET LOCAL app.authority_id` per transaction, set server-side from the authenticated staff record and never from client input.
