#!/usr/bin/env bash
# EXAMIS staging or production: install one release built by package.ps1.
# Run as "examis":
#
#   bash release.sh /tmp/examis-<sha>.tgz            # deploy
#   bash release.sh /tmp/examis-<sha>.tgz --seed     # first deploy: also seed
#
# --seed runs DatabaseSeeder: reference data everywhere, and on staging only
# the synthetic demo accounts and applications. Rollback: point "current" at
# the previous release, then reload PHP-FPM and restart the queue services.
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

# Each environment has its own database, never the live sis.
if grep -qx 'APP_ENV=production' "$SHARED/.env"; then
    ENVIRONMENT=production
    grep -qx 'DB_DATABASE=examis_production' "$SHARED/.env" || fail "DB_DATABASE must be examis_production"
else
    ENVIRONMENT=staging
    grep -qx 'APP_ENV=staging' "$SHARED/.env" || fail "APP_ENV must be staging or production"
    grep -qx 'DB_DATABASE=examis_staging' "$SHARED/.env" || fail "DB_DATABASE must be examis_staging"
fi
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
# The demo seeders build rows with factories, which need Faker, a dev
# package. Seeding staging installs dev packages for that step only; the site
# always runs without them. Production seeds reference data only.
DEV_FOR_SEED=""
[[ "$SEED" == "--seed" && "$ENVIRONMENT" == staging ]] && DEV_FOR_SEED=1
if [[ -n "$DEV_FOR_SEED" ]]; then
    log "Composer with dev packages, for seeding only"
    composer install --no-interaction --prefer-dist --no-progress
else
    log "Composer (no dev packages)"
    composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative --no-progress
fi

log "Migrations as the schema owner"
# Real environment variables win over .env, so only these commands use the owner.
(
    set -a
    . "$SHARED/.db-owner"
    set +a
    php artisan migrate --force
    if [[ "$SEED" == "--seed" ]]; then
        # Seeding twice would add a second set of demo applications.
        users="$(php artisan tinker --execute 'echo App\Models\User::query()->count();' | tail -n 1)"
        if [[ "$users" == "0" ]]; then
            php artisan db:seed --force
        else
            log "Skipping seeding: the database already has $users users"
        fi
    fi
)

if [[ -n "$DEV_FOR_SEED" ]]; then
    log "Composer: removing the dev packages again"
    composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative --no-progress
fi

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
# Over HTTPS on this server. Readiness also checks the database, cache and
# storage. The origin certificate is signed by Cloudflare's own CA, so curl
# cannot verify it here.
HOST="$(grep '^EXAMIS_STUDENTS_HOST=' "$SHARED/.env" | cut -d= -f2)"
curl -fsSk -o /dev/null -w 'GET /health/ready -> %{http_code}\n' \
    --resolve "${HOST}:443:127.0.0.1" "https://${HOST}/health/ready" \
    || fail "health check failed; previous releases are in $APP_ROOT/releases"

log "Keeping the last $KEEP releases"
ls -1dt "$APP_ROOT"/releases/* | tail -n +$((KEEP + 1)) | xargs -r rm -rf

log "Done: $(readlink -f "$APP_ROOT/current")"
