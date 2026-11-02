# ADR-0012: Staff sign in with email and password only

- Status: Accepted (2026-11-02), the owner's decision
- Amends: ADR-0011. Everything there about TOTP, recovery codes and two-factor resets no longer applies.

## Context

ADR-0011 required an authenticator app for every staff account. The owner decided to rely on email and password alone.

## Decision

- Staff sign in with their email and password. Fortify's two-factor feature is off, so its endpoints no longer exist.
- The enrolment enforcement, the recovery codes and the administrator's two-factor reset are removed.
- Everything else in ADR-0011 stays: separate portals, one-time links, no remember me, the idle limit, last-administrator protection, reviewed permissions and the audit trail.
- `examis:recover-admin` now only issues a one-time password link.
- The `users.two_factor_*` columns stay, unused, so no data is lost and the decision can be reversed.

## Consequences

- A stolen password is now enough to enter a staff account, including an administrator's, and with it the applicants' personal data and official decisions. This is the main accepted risk (THREAT-MODEL T4). Rate limits, the password policy and the audit trail reduce it but do not replace a second factor.
- Passwords must be unique to this system, and any suspicion of a leak is handled by issuing a new link at once.
