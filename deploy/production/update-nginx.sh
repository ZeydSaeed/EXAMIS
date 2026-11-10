#!/usr/bin/env bash
# Installs deploy/production/nginx/examis-production.conf on the production
# server, as root, from a copy of the deploy kit:
#
#   bash update-nginx.sh <students host> <staff host>
#
# Renders the host names in, keeps the live file as a dated backup, checks
# the whole configuration with nginx -t and reloads; if the check or the
# readiness probe afterwards fails, the backup goes back in place.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
STUDENTS_HOST="${1:?usage: update-nginx.sh <students host> <staff host>}"
STAFF_HOST="${2:?usage: update-nginx.sh <students host> <staff host>}"
LIVE=/etc/nginx/sites-available/examis-production

log() { printf '[update-nginx] %s\n' "$*"; }
fail() { printf '[update-nginx] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == 0 ]] || fail "run as root"
[[ -f "$LIVE" ]] || fail "no $LIVE"
[[ "$STUDENTS_HOST" =~ ^[a-z0-9.-]+$ && "$STAFF_HOST" =~ ^[a-z0-9.-]+$ ]] || fail "bad host name"

new="$(mktemp)"
sed -e "s/__STUDENTS_HOST__/$STUDENTS_HOST/g" -e "s/__STAFF_HOST__/$STAFF_HOST/g" \
    "$HERE/nginx/examis-production.conf" | tr -d '\r' > "$new"

if cmp -s "$new" "$LIVE"; then
    log "already up to date"
    rm -f "$new"
    exit 0
fi

backup="$LIVE.bak-$(date -u +%Y%m%dT%H%M%SZ)"
cp -p "$LIVE" "$backup"
install -m 644 "$new" "$LIVE"
rm -f "$new"

restore() {
    log "restoring $backup"
    cp -p "$backup" "$LIVE"
    nginx -t && systemctl reload nginx
    fail "$1"
}

nginx -t 2>&1 | sed 's/^/[nginx -t] /' || restore "nginx -t refused the new configuration"
nginx -t > /dev/null 2>&1 || restore "nginx -t refused the new configuration"
systemctl reload nginx
sleep 1

code="$(curl -sk -o /dev/null -w '%{http_code}' --resolve "${STUDENTS_HOST}:443:127.0.0.1" "https://${STUDENTS_HOST}/health/ready")"
[[ "$code" == 200 ]] || restore "local /health/ready answered $code after the reload"
log "installed (backup: $backup); local /health/ready 200"
