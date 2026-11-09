#!/usr/bin/env bash
# EXAMIS heartbeat, run as root every 5 minutes by examis-heartbeat.timer.
#
# Checks that the site answers, that the services it needs run, and that the
# disk and the replica's WAL are not filling up, then pings Healthchecks.io:
# the plain URL when all is well, <url>/fail with the problems otherwise. If
# the server itself is down no ping arrives, and Healthchecks.io alerts on the
# silence. Reads only HC_HEARTBEAT from /etc/examis-alerts/healthchecks.env.
set -uo pipefail

ENV_FILE="${EXAMIS_ALERTS_ENV:-/etc/examis-alerts/healthchecks.env}"
SHARED=/srv/examis/shared
DISK_LIMIT=85                                    # percent of / in use
WAL_LIMIT=$((2 * 1024 * 1024 * 1024))            # bytes the replica may lag (the slot stops at 4 GB)
SERVICES=(nginx php8.4-fpm postgresql redis-server clamav-daemon examis-queue examis-queue-fallback)
TIMERS=(examis-scheduler.timer examis-backup-6h.timer examis-backup-daily.timer examis-cloudflare-ips.timer
        examis-heartbeat.timer examis-backup-request.path examis-backup-delete.path)

url="$(grep -E '^HC_HEARTBEAT=https://' "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2-)"
problems=()

host="$(grep -E '^EXAMIS_STUDENTS_HOST=' "$SHARED/.env" | cut -d= -f2)"
code="$(curl -sk -o /dev/null -m 15 -w '%{http_code}' --resolve "${host}:443:127.0.0.1" "https://${host}/health/ready")"
[[ "$code" == 200 ]] || problems+=("site: /health/ready answered ${code}")

for unit in "${SERVICES[@]}" "${TIMERS[@]}"; do
    systemctl is-active --quiet "$unit" || problems+=("${unit} is $(systemctl is-active "$unit")")
done

failed="$(systemctl --failed --no-legend --plain | awk '{print $1}' | tr '\n' ' ')"
[[ -z "${failed// /}" ]] || problems+=("failed units: ${failed}")

used="$(df --output=pcent / | tail -1 | tr -dc '0-9')"
(( used < DISK_LIMIT )) || problems+=("disk / is ${used}% full")

while IFS='|' read -r slot _active lag; do
    [[ -n "$slot" ]] || continue
    (( lag < WAL_LIMIT )) || problems+=("replica slot ${slot} holds $((lag / 1024 / 1024)) MB of WAL (owner's PC offline?)")
done < <(runuser -u postgres -- psql -Atc \
    "select slot_name, active, coalesce(pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn), 0)::bigint from pg_replication_slots" 2>/dev/null)

if ((${#problems[@]})); then
    body="$(printf '%s\n' "${problems[@]}")"
    printf 'heartbeat FAIL:\n%s\n' "$body" >&2
    [[ -z "$url" ]] || curl -fsS -m 10 --retry 3 -o /dev/null --data-raw "$body" "${url}/fail"
    # Reported through the ping; failing the unit would only list itself next time.
    exit 0
fi

echo "heartbeat ok (disk ${used}%)"
[[ -z "$url" ]] || curl -fsS -m 10 --retry 3 -o /dev/null --data-raw "ok, disk ${used}%" "$url"
