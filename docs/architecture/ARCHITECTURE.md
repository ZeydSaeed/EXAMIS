# EXAMIS — Architecture

## 1. Context

```
 Applicant (phone / PC, PWA) ─┐                       ┌─► PostgreSQL (source of truth)
 Staff (reviewers, admins)  ──┼─► HTTPS ─► Nginx ─► Laravel 13 (PHP-FPM) ─┼─► Redis (cache, queues)   [prod]
 Public (track / verify QR) ──┘    WAF/CDN                │        │       ├─► Private object storage (documents)
                                                          │        └──────►├─► Mail / SMS provider (notifications)
                                                     Queue workers ────────┘
```

- **One deployable application**: a modular monolith (ADR-0001). Workers run the same codebase with `php artisan queue:work`.
- **Laravel is the source of truth for rules and permissions.** React renders state and collects input. It never decides eligibility, transitions or access.

## 2. Modules (namespaces inside the standard Laravel layout)

| Module                  | Responsibility                                                                                              | Main code                                                                                                           |
| ----------------------- | ----------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Identity & Access       | Student sign-in by identifier + trusted device or access code (ADR-0009), staff password login with 2FA, password reset by username (staff), deactivation, staff roles | `App\Actions\Fortify`, `Http\Controllers\Auth`, `Enums\Role`, `Enums\Permission`, `Actions\Staff\ManageStaffAccess` |
| Exam Periods            | Windows, eligibility (branch/category codes), required documents, numbering counter                         | `Models\ExamPeriod`, `DocumentType`, `Admin\ExamPeriodController`                                                   |
| Reference data          | Governorates, directorates, branches, categories (configurable, cached)                                     | `Models\ReferenceItem`, `Services\ReferenceData`                                                                    |
| Applications            | Draft lifecycle, optimistic locking, submission and numbering                                               | `Actions\Applications\{Start,SaveDraft,Submit}…`, `Services\Applications\ReferenceNumberGenerator`                  |
| Documents               | Upload validation, private storage, scanning, authorized download                                           | `Actions\Applications\StoreApplicationDocument`, `Jobs\ScanApplicationDocument`, `Services\Documents\*`             |
| Review & Decisions      | State machine, assignment, decisions, reopen                                                                | `Enums\ApplicationStatus`, `Actions\Applications\{Transition,Assign}Application`                                    |
| Receipts                | A4 Arabic PDF and QR verification                                                                           | `Services\Receipts\ReceiptRenderer`, `Public\ReceiptVerificationController`                                         |
| Notifications           | Status-change mail and database notifications (queued, after commit)                                        | `Notifications\ApplicationStatusChanged`                                                                            |
| Audit                   | Append-only audit trail and application history                                                             | `Services\Audit\AuditLogger`, `Models\{AuditEvent,ApplicationEvent}`, `Listeners\RecordAuthenticationActivity`      |
| Public portal & content | Managed pages, tracking, locale                                                                             | `Public\*Controller`, `Models\ContentPage`                                                                          |
| Reporting               | Aggregates and streamed CSV export                                                                          | `Admin\ReportController`                                                                                            |
| Operations              | Readiness probe, health, queue metrics                                                                      | `Services\Operations\HealthReport`, `Admin\OperationsController`                                                    |

## 3. Layers

- **Presentation**: thin controllers, Form Requests (validation and authorization), API Resources (explicit output shapes), Inertia pages.
- **Application**: Actions, one business operation each. They own transactions, row locks, events, audit, and after-commit notifications.
- **Domain**: Enums (`ApplicationStatus` transition table, `Role` → `Permission` map) and model rules (`missingRequirements`, `isVisibleToStaff`).
- **Infrastructure**: Eloquent/PostgreSQL, Storage disks, queue jobs, mPDF, scanner contract (`DocumentScanner`).

No Repository layer: Eloquent is the data-access layer, and query complexity sits in scopes.

## 4. Key flows

### Submission (idempotent)

```
POST /applications/{uuid}/submit (declaration=1)
  └ ApplicationPolicy@submit (owner, else 404)
  └ SubmitApplication
      BEGIN; SELECT … FROM applications WHERE id=? FOR UPDATE
      already submitted? → return unchanged (safe retry)
      period open? required fields + required non-rejected docs? eligibility re-check
      SELECT … FROM exam_periods WHERE id=? FOR UPDATE → last_sequence+1 → reference + mod-11 check digit
      UPDATE application (status, reference, timestamps, lock_version+1)
      INSERT application_events, audit_events
      COMMIT → queue ApplicationStatusChanged (afterCommit)
```

### Document upload

`UploadDocumentRequest` checks the extension allow-list, the content-sniffed MIME type and the per-type size. `StoreApplicationDocument` then writes the bytes to the private disk under `<application-uuid>/<48 random chars>`. In a transaction it marks the previous document of the same type as replaced and inserts the metadata (SHA-256, size, MIME), then queues `ScanApplicationDocument` after commit. The scanner verifies the signature, rejects active PDF content, and checks the image structure and pixel budget. Only `verified` documents are downloadable. A rejected file's bytes are deleted.

### Review

Reviewer: `submitted → under_review` (self-assigns) → `requires_correction` (reason). Applicant: corrects and resubmits (`resubmitted`). Supervisor: `under_review → accepted/rejected`. Reopening a decision needs `applications.reopen` and a reason. Each transition is validated against the locked row and appends history.

## 5. Cross-cutting concerns

| Concern             | Implementation                                                                                                                                                                             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Authorization       | Policies return `denyAsNotFound` for foreign records. One Gate per `Permission`. Route groups use `can:`. Actions re-check permissions (defense in depth).                                 |
| Security headers    | `SecurityHeaders` middleware: nonce-based CSP, `nosniff`, `DENY` framing, Referrer-Policy, Permissions-Policy, HSTS (production), `no-store` on authenticated responses                    |
| Request correlation | `AssignRequestId` puts the request ID in `Context`. It appears in logs and audit rows and is returned as `X-Request-Id`.                                                                   |
| i18n                | `SetLocale` (session → account → default `ar`). Backend `lang/*`. Frontend dictionary `lang/*/ui.php` shared as an Inertia once-prop. `<html dir>` is kept in sync by `useDocumentLocale`. |
| Concurrency         | Row locks plus short transactions with 3 deadlock retries. Optimistic `lock_version` for drafts. Unique constraints as the last line of defense.                                           |
| N+1 protection      | `Model::preventLazyLoading()` outside production                                                                                                                                           |
| PWA                 | `public/sw.js` caches static assets only (ADR-0008). `public/manifest.webmanifest`, `public/offline.html`.                                                                                 |

## 6. Scaling path (only when measurements require it)

1. Redis for cache and queue. Separate worker pools for the `documents` and `default` queues.
2. Horizontal PHP-FPM nodes behind the load balancer (stateless; sessions in DB/Redis, documents in object storage).
3. PgBouncer in transaction mode (the app keeps no session state in PostgreSQL).
4. Read replica for reports, with an explicit "reports may lag" UX.
5. Partition or archive `audit_events` and `application_events` by time when they exceed roughly 50M rows (see `docs/database/SCHEMA.md`).
