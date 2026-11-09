#!/usr/bin/env bash
# Installs the EXAMIS alerts. Run as root from this directory, with the
# Healthchecks.io ping URLs on stdin, one KEY=URL per line:
#
#   HC_HEARTBEAT=https://hc-ping.com/<uuid>
#   HC_BACKUP_6H=https://hc-ping.com/<uuid>
#   HC_BACKUP_DAILY=https://hc-ping.com/<uuid>
#
#   bash install-alerts.sh < urls.env
#
# The URLs are kept in /etc/examis-alerts/healthchecks.env (root, 600): the
# heartbeat and the backup job read them there. The application's own pings
# (staff access sync, integrity check) live in its .env instead. Safe to run
# again.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
ENV_DIR=/etc/examis-alerts
log() { printf '\n[install-alerts] %s\n' "$*"; }
fail() { printf '[install-alerts] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == 0 ]] || fail "run as root"

lines="$(tr -d '\r' | grep -E '^HC_(HEARTBEAT|BACKUP_6H|BACKUP_DAILY)=https://hc-ping\.com/[0-9a-f-]{36}$' || true)"
[[ "$(grep -c . <<<"$lines")" == 3 ]] || fail "expected the three HC_ ping URLs on stdin"

log "Ping URLs"
install -d -m 700 "$ENV_DIR"
umask 077
printf '%s\n' "$lines" > "$ENV_DIR/healthchecks.env.new"
mv -f "$ENV_DIR/healthchecks.env.new" "$ENV_DIR/healthchecks.env"
cut -d= -f1 "$ENV_DIR/healthchecks.env" | sed 's/^/  set /'

log "Heartbeat"
install -m 750 "$HERE/examis-heartbeat.sh" /usr/local/sbin/examis-heartbeat.sh
install -m 644 "$HERE/examis-heartbeat.service" "$HERE/examis-heartbeat.timer" /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now examis-heartbeat.timer
systemctl start examis-heartbeat.service
journalctl -u examis-heartbeat.service -n 3 --no-pager -o cat
