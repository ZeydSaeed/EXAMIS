# EXAMIS — Backup and restore

A backup only counts once a restore of it has been tested. Results of every drill are kept in `storage/restore-drills/` and summarized in §6.

## 1. Objectives (initial design targets — to be confirmed by the authority)

| Objective             | Target | How it is met                                                                                       |
| --------------------- | ------ | --------------------------------------------------------------------------------------------------- |
| RPO (max data loss)   | 15 min | PostgreSQL continuous WAL archiving (PITR) + object-storage versioning for documents                |
| RTO (time to service) | 4 h    | Scripted restore (`scripts/ops/restore-drill.sh`), documented runbook, pre-provisioned standby host |

These are targets, not guarantees. They hold only after a drill on production-sized data meets them.

## 2. What is backed up

| Data                               | Method                                                                       | Frequency           | Retention            | Location                                                       |
| ---------------------------------- | ---------------------------------------------------------------------------- | ------------------- | -------------------- | -------------------------------------------------------------- |
| PostgreSQL                         | WAL archiving (pgBackRest / WAL-G) + weekly base backup                      | continuous / weekly | 35 days PITR window  | off-site object storage, different account, object-lock (WORM) |
| PostgreSQL logical                 | `scripts/ops/backup.sh` (`pg_dump -Fc`, GPG-encrypted, checksummed manifest) | nightly             | 30 daily, 12 monthly | off-site                                                       |
| Documents (object storage)         | Bucket versioning + cross-region replication + object lock                   | continuous          | 35 days of versions  | second region / provider                                       |
| Documents (local disk deployments) | `backup.sh` tarball                                                          | nightly             | as above             | off-site                                                       |
| Configuration and secrets          | Secret manager's own versioning; never in backups or Git                     | on change           | —                    | secret manager                                                 |

Encryption: backups are encrypted with a key the application servers cannot read (GPG public key on the runner; the private key is held by the operations lead in the secret manager or an HSM). A compromised application host must not be able to decrypt or delete backups.

Monitoring: every backup job reports success or failure to monitoring. A missed or failed backup alerts within 1 hour (see `OPERATIONS.md`).

## 3. Consistency between database and documents

Documents are written to storage **before** their row is committed, and orphan files are reported, not deleted automatically. Restoring the database to time T and the documents to the same or a later time therefore leaves every referenced file present. Always restore documents to a point **at or after** the database recovery point. `php artisan examis:verify-integrity --hashes` proves it after a restore: missing files, hash mismatches, numbering counters, and submission history.

## 4. Restore procedure (summary — full steps in `docs/runbooks/RESTORE-DRILL.md`)

1. Choose the recovery point (incident time minus margin; check the audit log for the last good activity).
2. Restore PostgreSQL into an **isolated** instance or database (`*_restore_drill` for drills; a new instance for real recovery).
3. Restore documents to a timestamp ≥ the database recovery point.
4. Apply configuration and secrets from the secret manager (`APP_KEY` must match the original; otherwise encrypted 2FA secrets and sessions are unusable).
5. `php artisan migrate:status` (schema matches code) → `php artisan examis:verify-integrity --hashes`.
6. Compare counts with the backup manifest (`COUNTS.txt`).
7. Functional checks: staff login with 2FA; open a restored application; download a document; issue a receipt; verify its QR; submit a test application in a test period.
8. Record duration and any data gap; switch traffic (real recovery) or drop the drill database.

## 5. Scenarios covered by drills

| Scenario                   | Expected handling                                                                                                                                                                                 |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Primary database lost      | PITR restore to new instance → integrity check → repoint application                                                                                                                              |
| Corrupt latest backup      | `backup.sh` validates with `pg_restore --list`; drill uses checksums; fall back to the previous backup or PITR base                                                                               |
| Documents lost / deleted   | Restore bucket versions; `verify-integrity` lists any remaining gaps by count; affected applicants asked to re-upload via "requires correction"                                                   |
| Redis lost                 | No durable state in Redis (sessions/queues in DB locally; in production, queued notifications may be lost → re-send from `application_events` if needed); `examis:rescan-pending` re-queues scans |
| Queue worker down          | Jobs accumulate; operations page shows queue age; restart workers; scans re-queued by schedule                                                                                                    |
| Notification provider down | Notifications retry with backoff (5 tries); in-app notifications unaffected                                                                                                                       |
| PDF generation failure     | Receipts are generated on demand; no stored state to lose; fix and re-download                                                                                                                    |
| Bad release                | Roll back to the previous release directory (`OPERATIONS.md` §5); migrations are additive and backward compatible                                                                                 |
| Disk full                  | Alerts at 80%; documents on object storage; logs rotated daily                                                                                                                                    |

## 6. Drill log

| Date       | Environment                                                                                | Backup                                                                                     | Data size                                           | Duration | Result     | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| ---------- | ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------ | --------------------------------------------------- | -------- | ---------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-10-14 | Developer workstation, PostgreSQL 18.2 (Windows), isolated `examis_restore_drill` database | `scripts/ops/backup.sh` (pg_dump -Fc + documents tarball, GPG-encrypted, SHA-256 manifest) | 13 applications, 52 documents (synthetic demo data) | < 1 min  | **Passed** | Checksums OK; counts match the manifest; `migrate:status` OK; `examis:verify-integrity --hashes` passed; negative control detected a removed file; receipt generated; new submission did not collide (seq 12); staff login OK. Fixed during the drill: GNU tar needed `--force-local` for Windows paths. **Not yet proven:** PITR / WAL archiving and RTO at production data size (the 4 h target needs a drill on a production-sized copy). Report: `storage/restore-drills/20261014T194715Z.md`. |
