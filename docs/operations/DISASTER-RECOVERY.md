# EXAMIS — Disaster recovery plan

Initial targets: **RPO 15 minutes, RTO 4 hours**. These are design targets until a drill on production-sized data meets them (`BACKUP-RESTORE.md` §6).

## 1. Roles

| Role                                 | Responsibility                                                                        |
| ------------------------------------ | ------------------------------------------------------------------------------------- |
| Incident commander (operations lead) | Declares the disaster, decides the recovery point, communicates                       |
| Database operator                    | PITR restore, verification                                                            |
| Application operator                 | Deploy to the recovery environment, configuration, smoke tests                        |
| Authority liaison                    | Notices to applicants (e.g. deadline extension if the outage hit a submission window) |

## 2. Decision tree

1. **Is data intact?** If only compute is lost (node, AZ), redeploy the release on new nodes from the build artifact and point them at the existing database and storage. No restore needed.
2. **Database lost or corrupted?** Restore PITR to a new instance at the last consistent point before the incident. Check `audit_events` and `application_events` for the last valid activity.
3. **Documents lost?** Restore bucket versions to a time ≥ the DB recovery point.
4. **Security incident (compromise)?** Do NOT restore onto possibly compromised hosts. Rebuild infrastructure, rotate all secrets (`APP_KEY` rotation needs the `APP_PREVIOUS_KEYS` procedure), invalidate sessions, then restore data. Follow the incident runbook (`docs/runbooks/INCIDENTS.md`).

## 3. Recovery steps

1. Freeze writes: put the app in maintenance mode (`php artisan down --secret=<token>`) or drain the LB.
2. Restore the database per `docs/runbooks/RESTORE-DRILL.md` (real target instance, not `_restore_drill`).
3. Restore documents.
4. Deploy the last known-good release against the restored data. Load configuration from the secret manager.
5. `php artisan migrate:status`; `php artisan examis:verify-integrity --hashes`.
6. If counters are behind (only if the restore point is earlier than some issued numbers): set `exam_periods.last_sequence = max(issued sequence)`, then re-run integrity.
7. Smoke tests: staff login with 2FA, open an application, download a document, issue a receipt, submit a test application in a test period.
8. `php artisan up`. Monitor error rate and queue depth closely for 1 hour.
9. **Data gap handling**: applications submitted between the recovery point and the incident are lost from the database. Identify affected applicants from mail/notification provider logs (reference numbers sent), contact them, and ask them to resubmit. Their receipts remain verifiable only if resubmitted.

## 4. Exercises

| Exercise                                                             | Frequency                                       |
| -------------------------------------------------------------------- | ----------------------------------------------- |
| Logical restore drill (`restore-drill.sh`)                           | monthly                                         |
| PITR drill to a chosen timestamp                                     | quarterly                                       |
| Full DR simulation (new environment from scratch, timed against RTO) | twice a year, and before each major exam period |
| Tabletop (security compromise scenario)                              | yearly                                          |

All simulations happen in isolated environments. Production services are never stopped and production data is never deleted to "simulate" a disaster.
