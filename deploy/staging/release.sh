#!/usr/bin/env bash
# EXAMIS staging: install one release built by package.ps1. Run as "examis":
#
#   bash release.sh /tmp/examis-<sha>.tgz            # deploy
#   bash release.sh /tmp/examis-<sha>.tgz --seed     # first deploy: also seed
#
# --seed runs DatabaseSeeder, which outside production adds the synthetic demo
# accounts and applications (password "password"; Nginx asks for the staging
# password first). Rollback: point "current" at the previous release, then
# reload PHP-FPM and restart the queue services.
set -euo pipefail

APP_ROOT=/srv/examis
SHARED="$APP_ROOT/shared"
KEEP=5
PHP=8.4

TARBALL="${1:?usage: release.sh <tarball> [--seed]}"
SEED="${2:-}"

log() { printf '\n[release] %s\n' "$*"; }
fail() { printf '[release] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -un)" == "examis" ]] || fail "run as the examis user"
[[ -f "$TARBALL" ]] || fail "no such file: $TARBALL"
[[ -f "$SHARED/.env" ]] || fail "missing $SHARED/.env (run provision.sh first)"

# Staging only, synthetic data only.
grep -qx 'APP_ENV=staging' "$SHARED/.env" || fail "APP_ENV must be staging"
grep -qx 'DB_DATABASE=examis_staging' "$SHARED/.env" || fail "DB_DATABASE must be examis_staging"
! grep -qiE '^DB_DATABASE=sis$' "$SHARED/.env" || fail "refusing the live database"

RELEASE="$APP_ROOT/releases/$(date -u +%Y%m%d%H%M%S)"
log "Unpacking into $RELEASE"
mkdir -p "$RELEASE"
tar -xzf "$TARBALL" -C "$RELEASE"
[[ -f "$RELEASE/public/build/manifest.json" ]] || fail "release has no built assets (public/build)"

rm -rf "$RELEASE/storage"
ln -s "$SHARED/storage" "$RELEASE/storage"
ln -s "$SHARED/.env" "$RELEASE/.env"

cd "$RELEASE"
log "Composer (no dev packages)"
composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative --no-progress

log "Migrations as the schema owner"
# Real environment variables win over .env, so only this command uses the owner.
(
    set -a
    . "$SHARED/.db-owner"
    set +a
    php artisan migrate --force
    if [[ "$SEED" == "--seed" ]]; then
        php artisan db:seed --force
    fi
)

log "Caches"
php artisan optimize

log "Switching current"
ln -sfn "$RELEASE" "$APP_ROOT/current.new"
mv -Tf "$APP_ROOT/current.new" "$APP_ROOT/current"
sudo /usr/bin/systemctl reload "php${PHP}-fpm"
php artisan queue:restart
sudo /usr/bin/systemctl restart examis-queue.service
sudo /usr/bin/systemctl restart examis-queue-fallback.service

log "Health check"
sleep 2
curl -fsS -o /dev/null -w 'GET /up -> %{http_code}\n' \
    -H "Host: $(grep '^EXAMIS_STUDENTS_HOST=' "$SHARED/.env" | cut -d= -f2)" http://127.0.0.1/up \
    || fail "health check failed; previous releases are in $APP_ROOT/releases"

log "Keeping the last $KEEP releases"
ls -1dt "$APP_ROOT"/releases/* | tail -n +$((KEEP + 1)) | xargs -r rm -rf

log "Done: $(readlink -f "$APP_ROOT/current")"
