#!/usr/bin/env bash
# Run as root: puts the application's two Healthchecks.io ping URLs into the
# EXAMIS .env, from stdin (KEY=URL per line). The previous .env is kept next
# to it. Takes effect at the next release or `php artisan config:cache`.
set -euo pipefail
ENV_FILE=/srv/examis/shared/.env

lines="$(tr -d '\r' | sed 's/^\xef\xbb\xbf//' | grep -E '^HEALTHCHECK_(STAFF_ACCESS_SYNC|VERIFY_INTEGRITY)_URL=https://hc-ping\.com/[0-9a-f-]{36}$' || true)"
[[ "$(grep -c . <<<"$lines")" == 2 ]] || { echo "expected the two HEALTHCHECK_ URLs on stdin" >&2; exit 1; }

backup="$ENV_FILE.bak-$(date -u +%Y%m%dT%H%M%SZ)"
cp -p "$ENV_FILE" "$backup"
chmod 600 "$backup"

tmp="$(mktemp "$ENV_FILE.XXXXXX")"
grep -vE '^HEALTHCHECK_(STAFF_ACCESS_SYNC|VERIFY_INTEGRITY)_URL=|^# Healthchecks.io pings of the scheduled jobs$' "$ENV_FILE" > "$tmp" || true
printf '\n# Healthchecks.io pings of the scheduled jobs\n%s\n' "$lines" >> "$tmp"
chown --reference="$ENV_FILE" "$tmp"
chmod --reference="$ENV_FILE" "$tmp"
mv -f "$tmp" "$ENV_FILE"
echo "ok  application .env: $(grep -c '^HEALTHCHECK_' "$ENV_FILE") Healthchecks URLs (backup: $backup)"
