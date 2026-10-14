# Runbook — Restore drill

Frequency: monthly, and after any change to backup tooling or schema. Environment: an **isolated** host or database only. Never drop, stop or overwrite production resources.

## Preconditions

- [ ] Drill host has PostgreSQL client tools matching the server major version, PHP 8.4 and the EXAMIS release under test.
- [ ] A backup set is copied from off-site storage (not from the production host).
- [ ] The decryption key is retrieved by the operations lead (dual control). Do not leave it on disk.
- [ ] The drill database name ends with `_restore_drill`.

## Steps

1. **Start the clock** and note the backup timestamp you are restoring.
2. Run:
    ```bash
    BACKUP=/drill/backups/<timestamp> DRILL_DB=examis_restore_drill DRILL_DOCUMENTS=/drill/documents \
    PGHOST=<drill-db-host> PGUSER=<drill-role> PGPASSWORD=<from-secret-store> \
    scripts/ops/restore-drill.sh
    ```
    The script checks checksums, restores the database and documents, compares counts with the manifest, runs `migrate:status` and `examis:verify-integrity --hashes`, and writes `storage/restore-drills/<time>.md`.
3. For **PITR drills**, restore with pgBackRest/WAL-G to a target time instead of step 2's logical dump, then run step 5 of the script manually:
    ```bash
    DB_DATABASE=... DOCUMENTS_ROOT=... php artisan migrate:status && php artisan examis:verify-integrity --hashes
    ```
4. Point a drill instance of the application at the restored data (`.env` of the drill host) and perform the functional checks:
    - [ ] Staff login with 2FA works (proves `APP_KEY` is correct)
    - [ ] Open a restored application; the timeline is intact
    - [ ] Download one document of each type (proves files are restored and hashes match)
    - [ ] Issue a receipt PDF; scan its QR on the drill host
    - [ ] Submit a test application in a test period; its reference number does not collide (`verify-integrity` checks the counters)
5. **Stop the clock.** Record in `docs/operations/BACKUP-RESTORE.md` §6: date, backup, data size, duration, result, data gap (RPO evidence), problems.
6. Clean up: `dropdb examis_restore_drill`, delete `/drill/documents` and decrypted files, revoke temporary credentials. Keep only the report.

## If the drill fails

- Checksum mismatch → the backup set is corrupt: alert, and test the previous set immediately. The backup job must be investigated before the next night.
- Missing documents → check that documents were restored to a time ≥ the DB recovery point, and check bucket versioning and replication.
- Counter behind references → do **not** go live with this restore. Set `exam_periods.last_sequence` to the maximum issued sequence before accepting submissions.
