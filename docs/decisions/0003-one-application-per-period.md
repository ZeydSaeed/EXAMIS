# ADR-0003: One application per applicant per exam period

- Status: Accepted, pending confirmation by the examination authority (2026-10-14)

## Decision

- `applications` has `UNIQUE (user_id, exam_period_id)`. A correction cycle reuses the same row, and its history lives in `application_events`.
- Starting a draft is naturally idempotent: concurrent "start application" requests resolve to the same row. A unique-violation race is caught and the row is re-read.
- Withdrawal keeps the row with status `withdrawn`. Re-applying in the same period after withdrawal is not allowed by default. Changing that requires a new ADR (partial unique index).

## Consequences

- The duplicate-application guarantee holds even if the application layer is bypassed.
