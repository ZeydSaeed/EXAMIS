# Security testing

Order: static analysis and non-destructive tests first; active scanning only on the perf vhost and only with approval. Never against production or the public staging portals.

| Area | Existing proof | Added / planned | Kind |
| --- | --- | --- | --- |
| Authentication | `AuthenticationTest`, `StudentAccountsTest`, `OneTimeAccountTest` | k6 sign-in fails loudly on the code page; contract test: another student's code refused | automated |
| Authorization, role escalation | `PermissionMatrixTest`, `ApplicationPolicyTest`, `ReviewWorkflowTest` | k6/contract: reviewer → 403 on staff management | automated |
| IDOR | policies answer 404; E2E `security.spec.ts` | k6 `probeForeignApplication` (exact 404 on another student's application) | automated |
| Session | host-only cookies, staff idle 30 min, `PortalSeparationTest` | k6: student session on the staff host → staff sign-in; ZAP passive cookie flags | automated |
| CSRF | framework default | k6 sends the XSRF header; ZAP passive | automated |
| XSS | `PublicPortalTest`, React escaping, nonce CSP | ZAP active (approval) | automated + review |
| SQL injection | `PageRenderingTest::treats search input as data` | ZAP active on `q`, `track` | automated (approval) |
| File upload | `ApplicationDocumentTest`, `StructuralDocumentScannerTest` | antivirus still MISSING (B3); camera refusal is browser-only | review |
| Rate limiting | Pest limits tests | k6 records 429s; perf vhost header plan | automated |
| Secrets | gitleaks in CI; `.env` ignored | k6 never logs `QE_STAFF_PASSWORD` | automated |
| Dependencies | `composer audit`, `npm audit` in CI | Semgrep CE in CI (free) | automated |
| Headers, TLS | `PublicPortalTest` headers | testssl.sh against the public staging host is passive but reaches Cloudflare: approval | passive |
| Documentation drift | — | THREAT-MODEL T1 and T19 describe controls the code no longer has (`01-approved-version-baseline.md`) | human review |

## Levels

| Level | Examples | Automated? | Approval |
| --- | --- | --- | --- |
| Static | Larastan, Semgrep, audits, gitleaks | yes, CI | no (CI change needs approval once) |
| Passive | ZAP baseline on perf, header and cookie checks | yes | per session |
| Active | ZAP full scan with student and staff contexts on perf | yes, supervised | per run |
| Manual | business logic (one-time links, closing accounts, decision messages), accepted residual risks | no | owner sign-off |

Accepted residual risks waiting for the owner's sign-off (THREAT-MODEL §4): `style-src 'unsafe-inline'`, no antivirus, administrator could use a one-time link first, no staff second factor, decision messages over WhatsApp. Added in this phase: access codes are stored in plain text (ADR-0009, owner's choice), so a database or backup leak exposes every student account.

## Results (2026-11-07)

Static, passive and active levels have run: Semgrep, audits, Playwright, and ZAP on perf with guest, student and administrator identities. No High-risk alert. Findings and fixes: `13-defect-remediation.md` QE-005 to QE-010. Steps and evidence: `17-execution-log.md`. Still open: the manual business-logic review and the owner's sign-off on the residual risks above; testssl against the public host (needs approval); antivirus (B3).
