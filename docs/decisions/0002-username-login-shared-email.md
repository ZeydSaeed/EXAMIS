# ADR-0002: Username login; email is a shareable contact channel

- Status: Accepted (2026-10-14)

## Context

Families often share one email address or phone number. Several applicant accounts may share an email, but no account may see another account's applications.

## Decision

- The unique login identifier is `users.username` (lower-cased, `[a-z0-9._-]`, 4–32 characters).
- `users.email` is **not unique**. It is a notification and recovery channel only.
- Password reset is requested by **username**. The link is mailed to that account's email. Reset tokens are keyed by username (`getEmailForPasswordReset()` returns the username), so accounts that share an email never collide.
- Forgot-password always returns the same generic message (no account enumeration).
- Owning an email or phone grants **no** access to any application. Access is decided only by the authenticated account plus policies.
- Legal identity is not proven by account registration. Staff verify it at review against the uploaded identity documents (configurable per period).

## Consequences

- Users must remember a username. It is shown after registration and in the verification email.
