# EXAMIS — Security test plan

Scope: the EXAMIS application and its configuration. Dynamic or offensive testing runs only against local or staging environments owned by the project, never against production or third-party systems.

## 1. Automated on every change (CI: `.github/workflows/tests.yml`)

| Check                                                                                                   | Tool                                      | Gate                             |
| ------------------------------------------------------------------------------------------------------- | ----------------------------------------- | -------------------------------- |
| Code style                                                                                              | Laravel Pint, `vp check` (oxlint/oxfmt)   | must pass                        |
| Static analysis                                                                                         | Larastan level 7, `tsc --noEmit`          | must pass                        |
| Authorization matrix, IDOR, validation, uploads, sessions, rate limits, idempotency, audit immutability | Pest feature tests (`tests/Feature/**`)   | must pass                        |
| PostgreSQL-only constraints and triggers                                                                | Pest suite on a PostgreSQL 18 service     | must pass                        |
| PHP dependency advisories                                                                               | `composer audit`                          | no high/critical                 |
| JS dependency advisories                                                                                | `npm audit --omit=dev --audit-level=high` | no high/critical in runtime deps |
| Secrets in history                                                                                      | gitleaks                                  | no findings                      |
| End-to-end security behavior                                                                            | Playwright `security.spec.ts`             | must pass                        |

## 2. Test inventory (what proves what)

| Area                                          | Test                                                                                                                                                      |
| --------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Applicant isolation (404 for foreign records) | `ApplicationDraftTest`, `ApplicationSubmissionTest`, `ApplicationDocumentTest`, `ApplicationReceiptTest`, `ApplicationPolicyTest`, E2E `security.spec.ts` |
| Role matrix and separation of duties          | `ApplicationPolicyTest`, `ReviewWorkflowTest::restricts configuration sections…`                                                                          |
| Staff 2FA enforcement                         | `ReviewWorkflowTest::requires two-factor…`                                                                                                                |
| Brute force / enumeration                     | `AuthenticationTest`, `PasswordResetTest`, `RegistrationTest`, `PublicPortalTest` (tracking)                                                              |
| Shared-email isolation                        | `RegistrationTest`, `PasswordResetTest`                                                                                                                   |
| Mass assignment                               | `ApplicationDraftTest::ignores attempts to mass-assign…`                                                                                                  |
| Upload abuse                                  | `ApplicationDocumentTest`, `StructuralDocumentScannerTest`                                                                                                |
| XSS through content                           | `PublicPortalTest::shows published content pages…`                                                                                                        |
| Security headers / CSP                        | `PublicPortalTest::sends security headers…`, E2E `security.spec.ts`                                                                                       |
| PWA cache privacy                             | E2E `security.spec.ts::the service worker never stores private pages`                                                                                     |
| Audit immutability, no secrets in audit       | `AuditTrailTest`                                                                                                                                          |
| CSV injection                                 | `PageRenderingTest::exports CSV…`                                                                                                                         |
| Sensitive data not shared with the browser    | `PublicPortalTest::never shares the full user model…`                                                                                                     |
| SQL injection in search                       | `PageRenderingTest::treats search input as data`                                                                                                          |

## 3. Manual / periodic (before each release; staging)

1. **DAST**: OWASP ZAP baseline + authenticated scan (applicant and staff contexts) against staging:
   `docker run -t ghcr.io/zaproxy/zaproxy:stable zap-baseline.py -t https://staging.examis.example -r zap.html`
2. **Container/OS scan**: `trivy fs .` and `trivy image <image>` when images exist.
3. **TLS**: verify TLS 1.2+ only, HSTS, and a valid certificate chain (e.g. `testssl.sh`).
4. **Production configuration**: `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, `EXAMIS_REQUIRE_STAFF_2FA=true`, `DOCUMENT_SCANNER` ≠ `structural`, documents disk is a private bucket (block public access), `php artisan about` shows cached config and routes.
5. **Access review**: list staff with roles (`/admin/users`); remove dormant accounts; confirm administrators are not also supervisors unless approved.
6. **Audit review**: sample `application.viewed` and `document.viewed` events per reviewer for unusual volume.

## 4. Findings from this implementation round

| Finding                                                                        | Severity              | Status                                                    |
| ------------------------------------------------------------------------------ | --------------------- | --------------------------------------------------------- |
| Starter kit had no rate limit on registration or password reset                | Medium                | Fixed (`ThrottleAuthEndpoints`, `password-reset` limiter) |
| Starter kit forgot-password revealed account existence                         | Medium                | Fixed (generic response)                                  |
| Starter kit shared the full `User` model with the frontend                     | Low                   | Fixed (explicit allow-list)                               |
| Form Requests turned policy 404s into 403s (existence leak)                    | Medium                | Fixed (`Gate::authorize` in `authorize()`)                |
| Service worker reloaded the page on first install (could discard a typed form) | Low (UX/data loss)    | Fixed (reload only after an accepted update)              |
| No antivirus scanning                                                          | High (pre-production) | **Open**: Blocked B3                                      |
| `tinypool` critical advisory (dev tooling), `@babel/core` low                  | Low for runtime       | **Open**: dependency upgrade needs approval               |
| gitleaks / ZAP / trivy not executed in this environment (tools not installed)  | —                     | **Not tested**                                            |
