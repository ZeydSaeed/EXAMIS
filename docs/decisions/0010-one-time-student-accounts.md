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
- The last button of the form sends it: after a confirmation, the application
  goes to the review committee and the student lands on the final page. The
  account stays open there for as long as it takes, across sessions, and every
  other request, sign-out included, returns to that page.
- The account closes only when the form has been printed or downloaded
  (`users.closed_at`). Browsers report neither a finished print nor a saved
  file: the print dialog closing looks the same whether it printed or was
  cancelled. So the page asks the student once the dialog closes or the file
  has arrived, and only a yes closes the account.
- Closing is immediate: the access code and remembered devices are removed,
  `isActive()` is false and the session ends on the next request.
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
- A closed account's application can no longer be tracked or printed by the
  student: the tracking page refuses its reference number and offers no form
  for any application. Only staff can print the form again.
- A student who answers yes without a usable copy cannot get one online.
- A student who never answers yes keeps an open account, held on the final page.
- The tracking page takes a reference number alone, the owner's decision
  against the advice to require the birth date as well, and shows only the
  status of an account not yet closed.
