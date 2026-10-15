# EXAMIS project rules

EXAMIS is the external-examination e-application platform: applicants register, fill a multi-step form, upload documents, submit, get a reference number and PDF receipt, and follow their status; staff review and decide. Arabic (RTL) is the default language, English (LTR) is supported.

## Where things live

| Concern                                | Location                                                                                                       |
| -------------------------------------- | -------------------------------------------------------------------------------------------------------------- |
| Business operations (use cases)        | `app/Actions/Applications/*`, `app/Actions/Staff/*`                                                            |
| State machine (single source of truth) | `app/Enums/ApplicationStatus.php`                                                                              |
| Roles → permissions map                | `app/Enums/Role.php`, `app/Enums/Permission.php` (each permission is a Gate ability)                           |
| Authorization                          | `app/Policies/*`; staff visibility: `Application::scopeVisibleToStaff()` + `isVisibleToStaff()` (keep in sync) |
| Infrastructure services                | `app/Services/{Audit,Applications,Documents,Receipts,Operations}`                                              |
| Frontend pages                         | `resources/js/pages/{public,applicant,admin,auth,settings}`                                                    |
| UI strings                             | `lang/{ar,en}/ui.php` (frontend, via `useTrans()`), other `lang/*/*.php` (backend)                             |
| Architecture decisions                 | `docs/decisions/` (ADRs), overview in `docs/architecture/ARCHITECTURE.md`                                      |

## Non-negotiable rules

- Change application status ONLY through `TransitionApplication` or `SubmitApplication`. Never `update(['status' => …])` elsewhere.
- Every write that touches an application locks the row (`lockForUpdate()`) inside a short `DB::transaction(…, 3)` and re-checks state under the lock.
- `application_events` and `audit_events` are append-only. Never update or delete them; correct decisions by reopening with a reason.
- Applicants get 404 (not 403) for records they don't own: use `Response::denyAsNotFound()` and `Gate::authorize()` in Form Requests so the status survives.
- Login identifier is `username`; `email` is NOT unique (ADR-0002). Never look users up by email for authentication or reset.
- Documents live on the private `documents` disk under random keys, are served only through `ApplicationDocumentController@show`, and only when `status = verified`.
- Never log or audit passwords, OTPs, tokens, document contents or raw national IDs. Audit metadata must be non-sensitive.
- The service worker (`public/sw.js`) must never cache authenticated pages, documents, receipts or any non-GET request (ADR-0008).
- Shared Inertia props expose an explicit allow-list of user fields (`HandleInertiaRequests::sharedUser`). Do not share models directly.
- Nested API resource collections inside a resource must be `->resolve($request)`d, otherwise they serialize as `{data: [...]}`.
- Every new frontend string needs a key in BOTH `lang/ar/ui.php` and `lang/en/ui.php` (enforced by `TranslationCoverageTest`).
- Use logical Tailwind utilities (`ms-`, `me-`, `ps-`, `pe-`, `start`, `end`, `text-start`) so layouts work in RTL and LTR.

## Testing

- PHP: `php artisan test --compact` (SQLite in memory) and `composer test:pgsql` (PostgreSQL database `examis_testing`; also in CI). Static analysis: `composer types:check` (Larastan level 7).
- Frontend: `npm run types:check`, `npm run check`.
- E2E: `npm run build && npm run test:e2e` (isolated `database/e2e.sqlite`, seeded by `EndToEndSeeder`).
- Load tests: `tests/load/*.js` with k6, against local or staging only — never production.
