#!/usr/bin/env bash
# EXAMIS perf: install the approved staging release into the perf copy and
# rebuild its synthetic database. Run as root after provision-perf.sh:
#
#   bash release-perf.sh /tmp/examis-perf-overlay-<sha>.tgz <sha> [students]
#   bash release-perf.sh /tmp/examis-<sha>.tgz <sha> [students]
#
# With an overlay, the application is copied from /srv/examis/current (the
# approved release, with its built assets) and the overlay adds only
# database/seeders and tests/load from <sha>; anything else is refused.
# With a full release tarball from deploy/staging/package.ps1 (it holds
# public/build), perf runs that commit instead: a candidate is measured here
# before it reaches staging.
# The perf database is dropped and rebuilt every time: migrations, then
# PerfSeeder (default 1,000 students), which refuses any database that
# SyntheticTarget does not allow.
set -euo pipefail

PERF=/srv/examis-perf
ETC=/etc/examis-perf
APP="$PERF/app"

OVERLAY="${1:?usage: release-perf.sh <overlay.tgz> <sha> [students]}"
SHA="${2:?usage: release-perf.sh <overlay.tgz> <sha> [students]}"
STUDENTS="${3:-1000}"

log() { printf '\n[perf-release] %s\n' "$*"; }
fail() { printf '[perf-release] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == "0" ]] || fail "run as root"
[[ -f "$ETC/secrets" ]] || fail "run provision-perf.sh first"
[[ "$STUDENTS" =~ ^[0-9]+$ && "$STUDENTS" -ge 1 && "$STUDENTS" -le 50000 ]] || fail "students must be 1-50000"
FULL=no
if tar -tzf "$OVERLAY" | grep -qE '^(\./)?public/build/manifest\.json$'; then
    FULL=yes
elif tar -tzf "$OVERLAY" | grep -vE '^(\./)?(database/|database/seeders/.*|tests/|tests/load/.*|pax_global_header)?$' | grep -q .; then
    fail "the overlay may hold database/seeders and tests/load only"
fi
# shellcheck disable=SC1091
. "$ETC/secrets"

as_perf() { runuser -u examisperf -- env HOME="$PERF" COMPOSER_HOME="$PERF/.composer" "$@"; }

log "Stopping the perf session"
systemctl stop examis-perf.target examis-perf-fpm examis-perf-queue || true

rm -rf "$APP.new"
if [[ "$FULL" == "yes" ]]; then
    log "Unpacking the release candidate $SHA"
    mkdir -p "$APP.new"
    tar -xzf "$OVERLAY" -C "$APP.new"
    rm -rf "$APP.new/storage"
    SOURCE="candidate $SHA"
else
    log "Copying the approved release $(readlink -f /srv/examis/current)"
    rsync -a --exclude=/storage --exclude=/.env --exclude=/bootstrap/cache/*.php /srv/examis/current/ "$APP.new/"
    tar -xzf "$OVERLAY" -C "$APP.new"
    SOURCE="app code of $(readlink -f /srv/examis/current)"
fi
# Staging's cached config would point at examis_staging: none may survive.
rm -f "$APP.new"/bootstrap/cache/*.php
ln -sfn "$PERF/storage" "$APP.new/storage"
ln -sfn "$PERF/.env" "$APP.new/.env"
printf '%s\n' "$SHA" > "$APP.new/.perf-commit"
chown -R examisperf:www-data "$APP.new"
chmod -R g-w,o-rwx "$APP.new"
rm -rf "$APP.old"
[[ -d "$APP" ]] && mv "$APP" "$APP.old"
mv "$APP.new" "$APP"
rm -rf "$APP.old"

log "Environment file"
install -m 600 -o examisperf -g examisperf /dev/null "$PERF/.env"
cat > "$PERF/.env" <<EOF
# EXAMIS perf (load-test copy, 127.0.0.1:8443). Written by release-perf.sh.
APP_NAME=EXAMIS
APP_ENV=perf
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://students.perf.localhost:8443
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=7

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=examis_perf
DB_USERNAME=examis_perf_app
DB_PASSWORD=${DB_APP_PASSWORD}
DB_PERSISTENT=true

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

CACHE_STORE=failover
CACHE_FAILOVER_STORES=redis,database
QUEUE_CONNECTION=failover
QUEUE_FAILOVER_CONNECTIONS=redis,database
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6380

MAIL_MAILER=log

EXAMIS_STUDENTS_HOST=students.perf.localhost
EXAMIS_STAFF_HOST=staff.perf.localhost
EXAMIS_CSP_ENABLED=true
EXAMIS_HSTS_ENABLED=false
EXAMIS_LOGIN_ATTEMPTS_PER_IP_PER_HOUR=100

DOCUMENTS_DISK=documents
DOCUMENT_SCANNER=structural
DOCUMENT_SCAN_INLINE=true
EXAMIS_FORM_CACHE=true
DOCUMENTS_QUEUE=documents
EXAMIS_RECEIPT_FONT_DIR=${PERF}/fonts
EOF
install -m 600 -o examisperf -g examisperf /dev/null "$PERF/.db-owner"
printf 'DB_USERNAME=examis_perf_owner\nDB_PASSWORD=%s\n' "$DB_OWNER_PASSWORD" > "$PERF/.db-owner"

log "Clearing old perf files"
find "$PERF/storage/framework/cache" "$PERF/storage/framework/sessions" "$PERF/storage/framework/views" -mindepth 1 -delete
rm -rf "$PERF/storage/app/private/documents"

log "Starting PostgreSQL and Redis"
systemctl start postgresql@16-perf examis-perf-redis

cd "$APP"
log "Composer with dev packages (the demo seeder needs factories)"
as_perf composer install --no-interaction --prefer-dist --no-progress --quiet

as_owner() { as_perf bash -c 'set -a; . "$0"; set +a; exec "$@"' "$PERF/.db-owner" "$@"; }

log "Guard: the target must be examis_perf, marked synthetic"
as_owner php artisan tinker --execute 'Database\Seeders\Support\SyntheticTarget::assert(); echo DB::connection()->getDatabaseName(), ":", config("database.connections.pgsql.port"), PHP_EOL;' \
    | tail -n 1 | grep -qx 'examis_perf:5433' || fail "guard refused or wrong database"

log "Rebuilding the perf database"
start=$(date +%s)
as_owner php artisan migrate:fresh --force
as_owner env QE_STUDENTS="$STUDENTS" QE_STAFF_PASSWORD="$QE_STAFF_PASSWORD" php artisan db:seed --class=PerfSeeder --force
printf '[perf-release] seed: %s students in %ss\n' "$STUDENTS" "$(( $(date +%s) - start ))"
sudo -u postgres psql -p 5433 -d examis_perf -qAtX -c 'SELECT pg_stat_statements_reset()' >/dev/null

log "Composer without dev packages, caches"
as_perf composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative --no-progress --quiet
as_perf php artisan optimize

log "k6 suite for the qe user"
rm -rf "$PERF/qe/load"
cp -r "$APP/tests/load" "$PERF/qe/load"
chown -R qe:qe "$PERF/qe/load"

log "Starting the perf session"
systemctl start examis-perf.target
sleep 2
curl -fsSk -o /dev/null -w 'GET /up -> %{http_code}\n' \
    --resolve students.perf.localhost:8443:127.0.0.1 https://students.perf.localhost:8443/up \
    || fail "perf health check failed"

printf '%s\n' "$SOURCE" > "$APP/.perf-source"
log "Done: perf runs $SHA ($SOURCE), $STUDENTS students"
