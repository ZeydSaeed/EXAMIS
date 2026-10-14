# ADR-0005: State-based idempotency and optimistic locking

- Status: Accepted (2026-10-14)

## Context

Double-clicks, network timeouts, and retries must never create duplicate applications or submissions. Edits made in two browser sessions must not silently overwrite each other.

## Decision

1. **Submission is idempotent by state.** `SubmitApplication` locks the row. If the application is already `submitted` or later, it returns the existing reference number with no side effects. The client may safely retry after a timeout. The UI never claims success before the server confirms.
2. **Reference numbers** come from a per-period counter (`exam_periods.last_sequence`), incremented under `FOR UPDATE` inside the submission transaction. `UNIQUE (reference_number)` is the backstop. Format: `<PERIOD_CODE>-<6-digit sequence><mod-11 check digit>`, for example `EX2027-0001237`.
3. **Optimistic locking for drafts.** `applications.lock_version` travels with each save. A stale version is rejected with a user-facing "updated in another session, reload" error. Edits are never merged silently.
4. **Document replacement** sets `replaced_at` on the previous row and keeps it. A partial unique index allows only one _current_ document per type per application.
5. There is no generic `idempotency_keys` table. Every retriable write above already has a natural key. Revisit this when a public API is added.
6. **Deadlocks and timeouts:** transactions are short and lock one application row (plus the period counter at submission). `DB::transaction($callback, 3)` retries deadlocks. In production, PostgreSQL `lock_timeout` and `statement_timeout` are set on the application role (see `docs/database/SCHEMA.md`).
