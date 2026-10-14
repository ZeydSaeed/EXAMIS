# Runbooks — common incidents

Each entry: **symptom → check → action → verify**. Record every incident (time, impact, actions, follow-ups) in the incident log.

## Queue backlog growing (Operations page: oldest pending job > 5 min)

- Check: are the workers running (`systemctl status examis-worker@*`)? Recent `failed_jobs`? CPU and DB load?
- Action: restart workers (`php artisan queue:restart` + service restart); scale the `documents` pool if scans dominate; look for a poison job in `failed_jobs`. Inspect it with `php artisan queue:failed`, fix the cause, then `queue:retry <id>`.
- Verify: `oldest_pending_seconds` falls; documents move from pending to verified.

## Documents stuck "being checked"

- Check: Operations page `documents_pending_scan`; scanner availability; `storage/logs` for scanner errors.
- Action: restore the scanner; run `php artisan examis:rescan-pending --older-than=0`.
- Verify: count returns to near zero.

## Spike of failed logins / lockouts

- Check: Operations page counters; access logs for source IPs and user agents; `audit_events` `auth.failed` grouped by `ip_address`.
- Action: block abusive IPs or ASNs at the WAF; if one account is targeted, deactivate it temporarily (Admin → Users) and contact the owner.
- Verify: counters return to baseline; legitimate users can log in.

## 5xx errors after a release

- Check: error tracker by release; `X-Request-Id` from user reports → logs.
- Action: roll back (`OPERATIONS.md` §2) if the error rate is above 1% or a core flow is broken; then fix forward.
- Verify: error rate under the threshold; the E2E smoke (`npm run test:e2e -- --grep "applicant journey"`) passes against staging.

## Disk almost full

- Check: `df -h`, `du -sh storage/*` (logs, framework/cache, mpdf temp).
- Action: rotate or compress logs; clear `storage/app/mpdf` temp; confirm documents are on object storage, not local disk.
- Verify: usage below 70%.

## Database lock waits / timeouts

- Check: `docs/database/explain-checks.sql` (lock monitoring query); long-running transactions.
- Action: identify the blocking PID. Cancel reports or exports first (`pg_cancel_backend`). Never kill migrations mid-way without the database operator.
- Verify: no waiting locks; p95 latency normal.

## Suspected data exposure or account compromise

1. Contain: deactivate affected staff accounts (ends sessions immediately); rotate exposed credentials.
2. Preserve evidence: export the relevant `audit_events` (append-only) and access logs before rotation windows expire.
3. Assess: which records were viewed (`application.viewed`, `document.viewed`, `report.exported`) and by whom.
4. Notify according to the authority's legal obligations; document the decision.
5. Post-incident review within 5 working days, with actions tracked to closure.
