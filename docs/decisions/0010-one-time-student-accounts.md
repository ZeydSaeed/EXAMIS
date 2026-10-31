# ADR-0010: One-time student accounts

Date: 2026-10-31. Replaces the permanent-account parts of ADR-0009 and the
student side of ADR-0002.

## Decision

- Registration asks for a mobile number only, after the terms step. Each
  registration creates a new account with its own student number (the
  student's ID), `public_id` and `created_at`, even for a number used before.
- The student's account shows only the ID and the creation date. The mobile
  number stays on the record for staff and for the form.
- New student numbers have six digits; numbers already issued keep five. The
  operations page shows how many are used, because none is ever reused.
- One mobile number may belong to any number of accounts, so `users.phone` is
  no longer unique and no longer identifies an account.
- Students have no username: `users.username` is null for them. The column
  stays because staff sign in and reset passwords with it, and PostgreSQL
  requires it for staff (`users_staff_username_check`).
- The account closes the first time its owner opens the printable form of a
  submitted application (`users.closed_at`). Signing in stops at once: the
  access code and remembered devices are removed and `isActive()` is false.
- The session that printed stays usable for
  `EXAMIS_PRINT_WINDOW_MINUTES` (default 15) so a failed print can be repeated,
  then ends. Set it to 0 to end the session on the next request.
- A student who comes back registers again and gets a new ID.

## Consequences

- An application in `requires_correction` or `withdrawn` does not close the
  account when printed.
- Once closed, nobody can reopen an account, including staff. A correction
  requested after closing cannot be made by the student online.
- One person can hold several accounts, and so several applications in one
  period: `(user_id, exam_period_id)` no longer limits a person to one
  application.
- Removed usernames cannot be restored by the migration's `down()`.
