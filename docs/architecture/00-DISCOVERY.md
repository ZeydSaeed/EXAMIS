# EXAMIS — Discovery Report (Phase 0)

Date: 2026-10-14

## 1. Project state

The repository was an **existing, freshly scaffolded** Laravel React starter kit (4 commits, clean tree). It was not empty. Its structure is kept, and EXAMIS is built on top of it.

| Item       | Found                                                                                                          |
| ---------- | -------------------------------------------------------------------------------------------------------------- |
| OS         | Windows 10 Pro (Git Bash + PowerShell)                                                                         |
| PHP        | 8.4.16 NTS. Extensions: pdo_pgsql, pgsql, pdo_sqlite, redis, gd, intl, mbstring, fileinfo, sodium, zip         |
| Composer   | 2.9.5                                                                                                          |
| Laravel    | ^13.17 (framework), Fortify ^1.37, Inertia Laravel ^3.0, Wayfinder ^0.1, Boost ^2.10                           |
| Testing    | Pest ^5.3, Larastan ^3.9 (level 7), Pint                                                                       |
| Node / npm | 22.13.0 / 10.9.2 (npm lockfile; `pnpm-workspace.yaml` present but unused)                                      |
| Frontend   | React 19.2, Inertia React v3, TypeScript 5.7, Tailwind 4, Vite 8 via `vite-plus`, Radix UI / shadcn components |
| PostgreSQL | **18**, Windows service `postgresql-x64-18`, running on 127.0.0.1:5432 (`D:\PostgreSQL\18`)                    |
| Redis      | **Not running** (phpredis extension present, nothing listening on 6379)                                        |
| Docker     | **Not installed**                                                                                              |
| k6         | **Not installed**                                                                                              |
| Playwright | Not installed                                                                                                  |
| Herd       | Installed (CLI present)                                                                                        |
| CI         | `.github/workflows/tests.yml` (composer setup + `ci:check`)                                                    |

## 2. Baseline findings

- Baseline test suite **failed**: 31 errors, all "no such table: users". The cause was that `RefreshDatabase` was commented out in `tests/Pest.php`. **Fixed**: now 40/40 pass.
- PHPStan crashed when it hit the 128M memory limit. **Fixed**: `--memory-limit=1G` is now set in `composer types:check`.
- `npm run build` succeeds.
- Fortify defaults conflict with requirements:
    - Email is the unique login identifier, which conflicts with **shared family email**.
    - `forgot-password` and `register` have no rate limiting.
    - The forgot-password response reveals whether an account exists.

## 3. Blockers and external dependencies

| ID  | Item                            | Status                                                                         | Impact                                                                        | Recommendation                                                                                                                                     |
| --- | ------------------------------- | ------------------------------------------------------------------------------ | ----------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- |
| B1  | PostgreSQL credentials          | **Blocked**. The service runs, but credentials are not available to the agent. | DB-level CHECK constraints, triggers, and `EXPLAIN` could not be run locally. | Owner creates role `examis` + DBs `examis` / `examis_testing` and puts credentials in `.env`. CI runs the suite on a PostgreSQL service container. |
| B2  | Redis                           | Not available                                                                  | Cache, queue, and session use the `database` driver locally                   | Production: Redis for cache/queue, see ADR-0006                                                                                                    |
| B3  | Malware scanner (ClamAV)        | Not available                                                                  | Documents are verified structurally only                                      | A real scanner is required before production (`DOCUMENT_SCANNER=clamav`)                                                                           |
| B4  | SMS / WhatsApp / email provider | Not configured                                                                 | Mail goes to the `log` driver                                                 | Configure a provider. The notification channel contract is in place.                                                                               |
| B5  | Object storage (S3-compatible)  | Not configured                                                                 | Private local disk `documents`                                                | Set `DOCUMENTS_DISK=s3` + bucket policy (private)                                                                                                  |
| B6  | Docker, k6                      | Not installed                                                                  | Load tests and container builds cannot be executed here                       | Scripts are provided. Run them on a staging host.                                                                                                  |
| B7  | Regulatory rules                | Undecided                                                                      | Eligibility, document list, withdrawal policy, data retention period          | Configurable per exam period. Defaults are documented in ADR-0004.                                                                                 |

## 4. Key decisions (see `docs/decisions/`)

- ADR-0001: Modular monolith inside the standard Laravel layout (namespaces per module, no new base folders).
- ADR-0002: Login by a unique `username`. Email is a non-unique contact channel.
- ADR-0003: One application per applicant per exam period, enforced by a unique constraint.
- ADR-0004: Explicit application state machine with an append-only event history.
- ADR-0005: State-based idempotency plus optimistic locking instead of a generic idempotency-key table.
- ADR-0006: Database-backed drivers locally, Redis in production.
- ADR-0007: PDF receipts with mPDF (native Arabic shaping and bidi).
- ADR-0008: PWA caches public static assets only.

## 5. Risks

1. Regulatory requirements (eligibility, documents, identity proof) are not yet confirmed. Everything is data-driven per period to absorb changes.
2. PostgreSQL-specific guarantees (CHECK constraints, audit immutability trigger) are verified only when the suite runs on PostgreSQL (CI). Locally, the suite runs on SQLite.
3. A load target of 1000 concurrent users cannot be validated on this workstation. Results must come from a staging environment with documented specs.
