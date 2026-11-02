# ADR-0011: A separate staff portal with email sign-in and one-time links

- Status: Accepted (2026-11-02)
- Amends: ADR-0002 for staff accounts. Students are unchanged.

## Context

Staff signed in on the same host, session and guard as students, with a username, and two-factor authentication could be turned off by the account itself. Password resets needed mail, which the system does not have, and reset links reached the log when the mailer was `log`.

## Decision

**Two portals.** `Portal::Students` and `Portal::Staff` each have their own host (`EXAMIS_STUDENTS_HOST`, `EXAMIS_STAFF_HOST`), route file, guard (`web`, `staff`) and session cookie. A route answers on its own host and is a 404 on the other; any other host gets a 404, except the health checks.

- Each guard uses `AccountTypeUserProvider`, which resolves one account type, so a session id cannot cross over even if it is valid.
- Cookies are host-only, and take the `__Host-` prefix over HTTPS. Staff sessions use their own table `staff_sessions`, are encrypted, end when the browser closes or after 30 idle minutes, and use `SameSite=Strict`.
- `SelectPortal` is global middleware. Route middleware is too late: building a Fortify controller builds the guard and its session store.
- Fortify serves the staff portal only. Student registration and sign-out have their own routes.
- Wayfinder generates without hosts (`Portal::routeDomain()`), so the built frontend works on any host and port.
- Page history is encrypted over HTTPS and on `*.localhost`. Plain HTTP has no Web Crypto and Inertia stops navigating if asked.

**Sign-in.** Staff sign in with email, password and a TOTP code. The email is unique among staff, in any letter case, through a partial index; student emails stay shareable. Staff cannot change their own email. There is no "remember me" for staff. Two-factor authentication is always required, with no switch, on every staff route but enrolment and sign-out.

**TOTP.** Standard RFC 6238 (SHA-1, six digits, 30 seconds) through Fortify, with a 160-bit secret and a QR code rendered locally, so Google Authenticator, Microsoft Authenticator and Aegis all work and nothing leaves the server. No SMS, WhatsApp or paid service.

**One-time links.** There is no mail service. An administrator creates the account and gets a link to hand over through a private channel. The token is stored as a SHA-256 hash, lasts 48 hours, works once, and a newer link or a deactivation revokes it. It travels in the URL fragment, which browsers do not send, so it stays out of access logs and `Referer` headers; the page reads it and posts it. It is rendered directly into the response and never flashed, because a flash would write it to the session table. Public forgot-password is gone; a forgotten password means a new link. A link never touches two-factor authentication.

**Recovery codes.** Eight codes are stored as HMAC-SHA256 hashes, shown once, and each spent by a conditional update so two simultaneous uses cannot both win. Fortify's decryptable codes are no longer read and its endpoint for showing them is closed.

**Nobody resets themselves.** Disabling two-factor, replacing the secret, regenerating codes, reading the secret after enrolment, changing one's own roles or exceptions, issuing one's own link and deleting one's own account are all refused. An administrator resets another account. If no administrator can sign in, `php artisan examis:recover-admin` needs shell access and is audited.

**Administrators.** `examis:create-admin` creates the first administrators, one account for each person. The primary and backup administrators are separate people. The last active administrator cannot be deactivated or demoted: every such change locks all active administrators' rows, then re-reads in a new statement, because under READ COMMITTED the locking statement cannot see what the winner committed.

**Permissions.** `Role::permissions()` is an explicit list, never `Permission::cases()`. `PermissionMatrixTest` holds the reviewed grant of every role written out by hand, so a new permission fails the suite until it is reviewed. Administrators may decide applications, as the owner chose, but are never assigned them. Staff may have per-person exceptions, except on `admin.access`, `users.manage`, `staff.credentials` and `operations.view`, and administrators have none.

## Consequences

- Each portal needs its own hostname and certificate in production.
- Staff depend on an administrator for a forgotten password or a lost authenticator. That is the point, and it is why a backup administrator must exist.
- The administrator who hands over a link could use it first. The audit trail shows the link was issued and when it was spent, but cannot prevent this. Hand it over at once, and the staff member reports any link that does not work.
- Losing `APP_KEY` invalidates every TOTP secret and recovery code. Back it up apart from the database.
- PostgreSQL enforces staff email uniqueness and presence; a migration stops with a clear message if existing data breaks either rule.
- Staff accounts must be enrolled again once if they enrolled before this change, because recovery codes moved.
