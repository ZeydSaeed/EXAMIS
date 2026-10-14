# EXAMIS — منصة التقديم الإلكتروني للامتحانات الخارجية

External Examination Application System. Applicants register, fill a multi-step form, upload documents, submit, get a reference number and an Arabic PDF receipt, and follow their status. Staff review, request corrections, decide, and every action is audited. Arabic (RTL) by default, English (LTR) supported, installable as a PWA.

Built on Laravel 13 (PHP 8.4), Inertia v3, React 19, TypeScript, and Tailwind 4. PostgreSQL is the source of truth (SQLite is used for local tests).

## Quick start (local)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # reference data, content pages, demo period and accounts
npm run build                       # or: composer run dev
```

Demo accounts (local only, password `password`): `admin`, `supervisor`, `reviewer`, `auditor`, `student`. All data is synthetic.

To use PostgreSQL, set `DB_CONNECTION=pgsql` and the `DB_*` settings in `.env` (see `.env.example`).

## Quality gates

```bash
php artisan test --compact         # Pest: unit, feature, authorization, integrity
composer types:check               # Larastan level 7
vendor/bin/pint --test             # PHP style
npm run types:check && npm run check
npm run build && npm run test:e2e  # Playwright (isolated database/e2e.sqlite); E2E_ALL_BROWSERS=1 for Firefox/WebKit
```

Load tests (k6, staging only): see `docs/testing/PERFORMANCE.md`.

## Documentation

| Topic                          | Where                                                                     |
| ------------------------------ | ------------------------------------------------------------------------- |
| Discovery, current status      | `docs/architecture/00-DISCOVERY.md`, `docs/architecture/STATUS-REPORT.md` |
| Architecture                   | `docs/architecture/ARCHITECTURE.md`                                       |
| Decisions (ADRs)               | `docs/decisions/`                                                         |
| Database, indexes, constraints | `docs/database/SCHEMA.md`                                                 |
| Threat model, security tests   | `docs/security/`                                                          |
| Test strategy and traceability | `docs/testing/TEST-STRATEGY.md`                                           |
| Operations, backup/restore, DR | `docs/operations/`                                                        |
| Runbooks                       | `docs/runbooks/`                                                          |
| Rules for AI coding agents     | `AGENTS.md`, `CLAUDE.md` (source: `.ai/guidelines/examis.md`)             |
