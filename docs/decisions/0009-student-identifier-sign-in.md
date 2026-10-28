# ADR-0009: Student sign-in by identifier, trusted device or access code

Status: Accepted (2026-10-29)

## Context

Students should register with a username and a mobile number only, without
SMS/WhatsApp verification or paid services, and sign in with any one of:
username, mobile number, student number or QR code. With no password and
no verified phone, any of these identifiers alone would let anyone who
knows it impersonate the student.

## Decision

- Registration creates a permanent account with a random `public_id` (QR),
  a unique five-digit `student_number` (10000–99999, UNIQUE + CHECK) and a
  private **access code** (`XXXXX-XXXXX`, stored hashed, shown once).
- `/login` takes one identifier. If the browser holds a valid **trusted
  device** cookie for that account, the student is signed in. Otherwise
  the access code is required. Unknown identifiers go through the same
  code step, so the form does not reveal which accounts exist.
- Trusted devices: encrypted HttpOnly cookie `id|token`; only the SHA-256
  of the token is stored; 180-day expiry; revocable.
- The QR encodes `/s/{public_id}`: no personal data, and it goes through
  the same checks. It is not an open key to the account.
- Code attempts are limited per account (10/hour) as well as per IP and
  session. Failures and lockouts are audited.
- The mobile number is UNIQUE among accounts because it is a sign-in
  identifier. It is **not verified**, so it does not prove ownership.
- Recovery: the student can regenerate the code from a signed-in session
  (other devices are signed out). An administrator (`users.manage`) can
  reset it after an in-person identity check, and the reset is audited.
- Staff keep username + password + 2FA at `/staff/login` (Fortify's
  `POST /login`). Older applicant accounts with a password can still use it.
- Student numbers are allocated randomly, with retry on unique violation,
  each attempt in its own savepoint. A scan fallback runs when the range
  is crowded. When the range is exhausted, allocation stops and the
  account works without a number. The number and `public_id` cannot be
  changed once assigned.
- Students cannot delete their own account (retention policy pending).
- Clean-up (migration `2026_10_29_010000`): `users.email_verified_at` and
  the `passkeys` table were dropped. Applicant passwords, emails and
  remember tokens were cleared. Status changes now reach students as
  in-portal notifications only. Older students sign in after an
  administrator issues them an access code.

## Consequences

- Anyone with physical access to a remembered device can sign in by typing
  the identifier. Remembering a device is opt-in at the code step.
- A lost access code with no remembered device needs a visit to the office.
- In-browser QR scanning is not provided: the camera is blocked by
  `Permissions-Policy`. The phone's own camera opens the link instead.
