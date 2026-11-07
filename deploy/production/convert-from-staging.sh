#!/usr/bin/env bash
# EXAMIS: turn the staging server into production (owner's decision,
# 2026-11-08). Run as root on the server, from the folder holding this kit:
#
#   bash convert-from-staging.sh convert <tarball> "<admin name>" <admin email>
#   bash convert-from-staging.sh drop-staging      # only after convert passed
#
# convert: takes a last encrypted backup of the staging database, switches
# the perf copy off for good, creates the empty examis_production database,
# points .env and Nginx at students./staff.mahani-iq.com without the staging
# password, releases with reference data only, backs production up, and
# creates the first administrator (one-time activation link, root-only file).
# drop-staging: deletes the examis_staging database, which the backup above
# still holds. Never touches sis. Prints no secret.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
APP_ROOT=/srv/examis
SHARED="$APP_ROOT/shared"
ENV_FILE="$SHARED/.env"
STUDENTS_HOST=students.mahani-iq.com
STAFF_HOST=staff.mahani-iq.com
NEW_DB=examis_production
OLD_DB=examis_staging
LINK_FILE=/root/examis-admin-activation.txt

log() { printf '\n[convert] %s\n' "$*"; }
fail() { printf '[convert] ERROR: %s\n' "$*" >&2; exit 1; }
psql_root() { runuser -u postgres -- psql -v ON_ERROR_STOP=1 -qAt "$@"; }
db_exists() { [[ "$(psql_root -c "SELECT 1 FROM pg_database WHERE datname = '$1'")" == 1 ]]; }
set_env() {
    if grep -q "^$1=" "$ENV_FILE"; then
        sed -i "s#^$1=.*#$1=$2#" "$ENV_FILE"
    else
        printf '%s=%s\n' "$1" "$2" >> "$ENV_FILE"
    fi
}
backup_daily() {
    systemctl start examis-backup@daily.service
    [[ "$(systemctl show -p Result --value examis-backup@daily.service)" == success ]] || fail "daily backup failed"
    log "Backup: $(cat /var/backups/examis/last-daily)"
}

[[ "$(id -u)" == 0 ]] || fail "run as root"
[[ -f "$ENV_FILE" ]] || fail "missing $ENV_FILE"
! grep -qiE '^DB_DATABASE=sis$' "$ENV_FILE" || fail "refusing the live database"

convert() {
    local tarball="${1:?tarball}" name="${2:?admin name}" email="${3:?admin email}"
    [[ -f "$tarball" ]] || fail "no such file: $tarball"
    grep -qx 'APP_ENV=staging' "$ENV_FILE" || fail "APP_ENV is not staging: already converted?"
    grep -qx "DB_DATABASE=$OLD_DB" "$ENV_FILE" || fail "DB_DATABASE is not $OLD_DB"
    ! db_exists "$NEW_DB" || fail "$NEW_DB already exists"
    openssl x509 -in /etc/ssl/examis/origin.pem -noout -ext subjectAltName | grep -q 'DNS:\*\.mahani-iq\.com' \
        || fail "the origin certificate does not cover *.mahani-iq.com"
    [[ -f "$HERE/nginx/examis-production.conf" ]] || fail "missing nginx/examis-production.conf"

    log "Last backup of the staging database"
    backup_daily

    log "Perf copy and ZAP: off for good"
    # The unit files move to a root-only folder (files and data stay, nothing
    # can start them); the perf cluster already starts manually only.
    local units=(examis-perf.target examis-perf-fpm.service examis-perf-queue.service examis-perf-redis.service)
    systemctl stop "${units[@]}" postgresql@16-perf 2>/dev/null || true
    install -d -m 700 /root/examis-perf-disabled
    for unit in "${units[@]}"; do
        [[ -f "/etc/systemd/system/$unit" ]] && mv "/etc/systemd/system/$unit" /root/examis-perf-disabled/
    done
    systemctl daemon-reload
    rm -f /etc/nginx/sites-enabled/examis-perf

    log "Empty database $NEW_DB (same roles and grants as staging)"
    runuser -u postgres -- createdb -O examis_owner "$NEW_DB"
    psql_root -d "$NEW_DB" <<SQL
REVOKE CONNECT ON DATABASE ${NEW_DB} FROM PUBLIC;
GRANT CONNECT ON DATABASE ${NEW_DB} TO examis_owner, examis_app;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO examis_app;
ALTER DEFAULT PRIVILEGES FOR ROLE examis_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO examis_app;
ALTER DEFAULT PRIVILEGES FOR ROLE examis_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO examis_app;
SQL

    log ".env: production (the staging copy is kept, root only)"
    install -m 600 "$ENV_FILE" "/root/env-staging-final-$(date -u +%Y%m%dT%H%M%SZ)"
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "https://$STUDENTS_HOST"
    set_env DB_DATABASE "$NEW_DB"
    set_env EXAMIS_STUDENTS_HOST "$STUDENTS_HOST"
    set_env EXAMIS_STAFF_HOST "$STAFF_HOST"
    set_env EXAMIS_HSTS_ENABLED true
    set_env APP_KEY "$(cd "$APP_ROOT/current" && runuser -u examis -- php artisan key:generate --show --no-interaction)"
    grep -E '^(APP_ENV|APP_URL|DB_DATABASE|EXAMIS_STUDENTS_HOST|EXAMIS_STAFF_HOST|DOCUMENT_SCANNER|MAIL_MAILER)=' "$ENV_FILE"

    log "Nginx: production hosts, no staging password"
    sed -e "s/__STUDENTS_HOST__/$STUDENTS_HOST/g" -e "s/__STAFF_HOST__/$STAFF_HOST/g" \
        "$HERE/nginx/examis-production.conf" > /etc/nginx/sites-available/examis-production
    ln -sfn /etc/nginx/sites-available/examis-production /etc/nginx/sites-enabled/examis-production
    rm -f /etc/nginx/sites-enabled/examis-staging
    nginx -t
    systemctl reload nginx

    log "Release with reference data only"
    install -o examis -m 644 "$tarball" /tmp/examis-production.tgz
    install -m 755 "$HERE/../staging/release.sh" "$APP_ROOT/deploy/release.sh"
    runuser -u examis -- bash "$APP_ROOT/deploy/release.sh" /tmp/examis-production.tgz --seed

    log "First production backup (the backup script now allows $NEW_DB)"
    install -m 750 "$HERE/../backup/examis-backup.sh" /usr/local/sbin/examis-backup.sh
    backup_daily

    log "First administrator"
    install -m 600 /dev/null "$LINK_FILE"
    (cd "$APP_ROOT/current" && runuser -u examis -- php artisan examis:create-admin "$name" "$email") > "$LINK_FILE"
    grep -q 'http' "$LINK_FILE" || fail "no activation link was printed"
    log "Activation link saved in $LINK_FILE (root only)"

    log "Checks"
    for host in "$STUDENTS_HOST" "$STAFF_HOST"; do
        curl -sk -o /dev/null -w "$host / -> %{http_code}\n" --resolve "$host:443:127.0.0.1" "https://$host/"
    done
    curl -sk -o /dev/null -w "students-staging -> %{http_code} (expect 000: refused)\n" \
        --resolve "students-staging.mahani-iq.com:443:127.0.0.1" https://students-staging.mahani-iq.com/ || true
    cd "$APP_ROOT/current" && runuser -u examis -- php artisan tinker --execute \
        'echo "users=", App\Models\User::query()->count(), " applications=", App\Models\Application::query()->count(), PHP_EOL;'
    log "convert done. Next: Cloudflare DNS, then drop-staging."
}

drop_staging() {
    grep -qx 'APP_ENV=production' "$ENV_FILE" || fail "run convert first"
    grep -qx "DB_DATABASE=$NEW_DB" "$ENV_FILE" || fail "production must use $NEW_DB"
    db_exists "$OLD_DB" || { log "$OLD_DB is already gone"; return; }
    compgen -G '/var/backups/examis/daily/*' > /dev/null || fail "no daily backup on this server"
    curl -fsk -o /dev/null --resolve "$STUDENTS_HOST:443:127.0.0.1" "https://$STUDENTS_HOST/health/ready" \
        || fail "production is not ready; keeping $OLD_DB"
    log "Dropping $OLD_DB (synthetic data; the encrypted backups still hold it)"
    runuser -u postgres -- dropdb --if-exists "$OLD_DB"
    psql_root -c 'SELECT datname FROM pg_database WHERE NOT datistemplate'
}

case "${1:-}" in
    convert) shift; convert "$@" ;;
    drop-staging) drop_staging ;;
    *) fail "usage: convert <tarball> \"<admin name>\" <admin email> | drop-staging" ;;
esac
