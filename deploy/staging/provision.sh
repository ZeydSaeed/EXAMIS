#!/usr/bin/env bash
# EXAMIS staging: one-time setup of a fresh Ubuntu 24.04 server (ARM64 or x86).
# Run as root on the server, from a copy of this folder:
#
#   STUDENTS_HOST=students-staging.example.com \
#   STAFF_HOST=staff-staging.example.com bash provision.sh
#
# Before running: put the Cloudflare Origin Certificate for the domain at
# /etc/ssl/examis/origin.pem and its key at /etc/ssl/examis/origin.key.
#
# Safe to re-run: generated secrets are kept, configuration files are rewritten.
# It never touches any database except the staging one it creates, and it
# refuses to run against a database named "sis".
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_USER=examis
APP_ROOT=/srv/examis
DB_NAME=examis_staging
PHP=8.4
SECRETS=/root/examis-staging-secrets.txt
TLS_DIR=/etc/ssl/examis

: "${STUDENTS_HOST:?Set STUDENTS_HOST, the students portal host name}"
: "${STAFF_HOST:?Set STAFF_HOST, the staff portal host name}"

log() { printf '\n[provision] %s\n' "$*"; }
fail() { printf '[provision] ERROR: %s\n' "$*" >&2; exit 1; }
# Hex only: safe in SQL, sed and .env without quoting, and no pipeline that
# SIGPIPE could break under pipefail.
secret() { openssl rand -hex 24; }
# grep -q in a pipe can exit before the writer finishes; under pipefail the
# writer's SIGPIPE then fails the check, so pipes use grep >/dev/null instead.

# Reads a saved secret, or creates and saves it on first run.
keep_secret() {
    local name="$1" value
    touch "$SECRETS" && chmod 600 "$SECRETS"
    value="$(grep -E "^${name}=" "$SECRETS" | cut -d= -f2- || true)"
    if [[ -z "$value" ]]; then
        value="$(secret)"
        echo "${name}=${value}" >> "$SECRETS"
    fi
    printf '%s' "$value"
}

[[ $EUID -eq 0 ]] || fail "run as root"
. /etc/os-release
[[ "${VERSION_ID}" == "24.04" ]] || fail "expected Ubuntu 24.04, found ${PRETTY_NAME}"
[[ "$DB_NAME" != "sis" ]] || fail "refusing to use the live database name"
log "Ubuntu ${VERSION_ID} on $(uname -m)"

log "Origin certificate (Cloudflare Origin CA, SSL mode Full (strict))"
[[ -s "$TLS_DIR/origin.pem" && -s "$TLS_DIR/origin.key" ]] \
    || fail "put the Cloudflare Origin Certificate at $TLS_DIR/origin.pem and its key at $TLS_DIR/origin.key"
cert_key="$(openssl x509 -noout -pubkey -in "$TLS_DIR/origin.pem" | sha256sum)"
key_key="$(openssl pkey -pubout -in "$TLS_DIR/origin.key" | sha256sum)"
[[ "$cert_key" == "$key_key" ]] || fail "origin.key does not belong to origin.pem"
for host in "$STUDENTS_HOST" "$STAFF_HOST"; do
    openssl x509 -noout -checkhost "$host" -in "$TLS_DIR/origin.pem" | grep 'does match' >/dev/null \
        || fail "the origin certificate does not cover $host"
done
chown -R root:root "$TLS_DIR"
chmod 755 "$TLS_DIR"
chmod 644 "$TLS_DIR/origin.pem"
chmod 600 "$TLS_DIR/origin.key"

log "System packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get upgrade -yq
apt-get install -yq software-properties-common ca-certificates curl unzip git \
    ufw fail2ban unattended-upgrades apache2-utils \
    nginx redis-server postgresql postgresql-contrib
add-apt-repository -y ppa:ondrej/php
apt-get update -q
apt-get install -yq "php${PHP}-fpm" "php${PHP}-cli" "php${PHP}-pgsql" "php${PHP}-redis" \
    "php${PHP}-gd" "php${PHP}-mbstring" "php${PHP}-xml" "php${PHP}-zip" "php${PHP}-curl" \
    "php${PHP}-intl" "php${PHP}-bcmath" "php${PHP}-opcache"

if ! command -v composer >/dev/null; then
    log "Composer (installer checked against its published signature)"
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    actual="$(sha384sum /tmp/composer-setup.php | cut -d' ' -f1)"
    [[ "$expected" == "$actual" ]] || fail "Composer installer signature mismatch"
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

log "Swap (2 GB)"
if ! swapon --show | grep /swapfile >/dev/null; then
    fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
sysctl -q -w vm.swappiness=10
echo 'vm.swappiness=10' > /etc/sysctl.d/90-examis.conf

log "Firewall (web ports open to Cloudflare only), SSH hardening, security updates"
ufw allow OpenSSH >/dev/null
ufw --force enable >/dev/null
# sshd keeps the first value it reads, and drop-ins are read in name order:
# 00- comes before the image's 50-cloud-init.conf, which may enable passwords.
rm -f /etc/ssh/sshd_config.d/90-examis.conf
cat > /etc/ssh/sshd_config.d/00-examis.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
EOF
sshd -t
systemctl try-reload-or-restart ssh
sshd -T | grep -x 'passwordauthentication no' >/dev/null || fail "SSH password sign-in is still enabled"
systemctl enable --now fail2ban unattended-upgrades >/dev/null

log "Application user and folders"
if ! id "$APP_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$APP_USER"
fi
# The deploy user signs in with the same key as root.
install -d -m 700 -o "$APP_USER" -g "$APP_USER" "/home/${APP_USER}/.ssh"
install -m 600 -o "$APP_USER" -g "$APP_USER" /root/.ssh/authorized_keys "/home/${APP_USER}/.ssh/authorized_keys"
install -d -m 750 -o "$APP_USER" -g www-data "$APP_ROOT" "$APP_ROOT/releases" "$APP_ROOT/shared" "$APP_ROOT/shared/fonts"
# Parents are listed too: install -d leaves the ones it creates owned by root,
# and the app makes its own folders in them (storage/app/mpdf for the PDF
# library, receipts under storage/app/private).
for dir in . app app/private app/private/documents app/public framework framework/cache \
    framework/cache/data framework/sessions framework/views logs; do
    install -d -m 770 -o "$APP_USER" -g www-data "$APP_ROOT/shared/storage/$dir"
done
cat > /etc/sudoers.d/examis <<EOF
${APP_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php${PHP}-fpm, /usr/bin/systemctl restart examis-queue.service, /usr/bin/systemctl restart examis-queue-fallback.service
EOF
chmod 440 /etc/sudoers.d/examis
visudo -cf /etc/sudoers.d/examis >/dev/null

log "PostgreSQL: owner role for migrations, app role for runtime"
OWNER_PASSWORD="$(keep_secret DB_OWNER_PASSWORD)"
APP_PASSWORD="$(keep_secret DB_APP_PASSWORD)"
install -m 644 "$HERE/postgresql/examis-tuning.conf" /etc/postgresql/16/main/conf.d/examis-tuning.conf
systemctl restart postgresql
# Statement logging is off for this session so the passwords never reach a log.
sudo -u postgres psql -v ON_ERROR_STOP=1 -q <<SQL
SET log_statement = 'none';
SET log_min_duration_statement = -1;
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'examis_owner') THEN
        CREATE ROLE examis_owner LOGIN;
    END IF;
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'examis_app') THEN
        CREATE ROLE examis_app LOGIN;
    END IF;
END
\$\$;
ALTER ROLE examis_owner PASSWORD '${OWNER_PASSWORD}';
ALTER ROLE examis_app PASSWORD '${APP_PASSWORD}';
ALTER ROLE examis_app SET lock_timeout = '5s';
ALTER ROLE examis_app SET statement_timeout = '30s';
ALTER ROLE examis_app SET idle_in_transaction_session_timeout = '60s';
SQL
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname = '${DB_NAME}'" | grep 1 >/dev/null; then
    sudo -u postgres createdb -O examis_owner "$DB_NAME"
fi
sudo -u postgres psql -v ON_ERROR_STOP=1 -q -d "$DB_NAME" <<SQL
REVOKE CONNECT ON DATABASE ${DB_NAME} FROM PUBLIC;
GRANT CONNECT ON DATABASE ${DB_NAME} TO examis_owner, examis_app;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO examis_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO examis_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO examis_app;
ALTER DEFAULT PRIVILEGES FOR ROLE examis_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO examis_app;
ALTER DEFAULT PRIVILEGES FOR ROLE examis_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO examis_app;
SQL
# Release scripts run migrations as the owner; only the deploy user may read this.
install -m 600 -o "$APP_USER" -g "$APP_USER" /dev/null "$APP_ROOT/shared/.db-owner"
printf 'DB_USERNAME=examis_owner\nDB_PASSWORD=%s\n' "$OWNER_PASSWORD" > "$APP_ROOT/shared/.db-owner"

log "Redis: local only, password, memory cap that never evicts queued jobs"
REDIS_PASSWORD="$(keep_secret REDIS_PASSWORD)"
cat > /etc/redis/conf.d-examis.conf <<EOF
requirepass ${REDIS_PASSWORD}
maxmemory 256mb
maxmemory-policy volatile-lru
EOF
chown redis:redis /etc/redis/conf.d-examis.conf && chmod 640 /etc/redis/conf.d-examis.conf
grep -q 'conf.d-examis.conf' /etc/redis/redis.conf || echo 'include /etc/redis/conf.d-examis.conf' >> /etc/redis/redis.conf
systemctl restart redis-server

log "PHP-FPM pool and php.ini overrides"
install -m 644 "$HERE/php/examis-pool.conf" "/etc/php/${PHP}/fpm/pool.d/examis.conf"
install -m 644 "$HERE/logrotate/examis" /etc/logrotate.d/examis
install -m 644 "$HERE/php/examis-php.ini" "/etc/php/${PHP}/fpm/conf.d/90-examis.ini"
install -m 644 "$HERE/php/examis-php.ini" "/etc/php/${PHP}/cli/conf.d/90-examis.ini"
rm -f "/etc/php/${PHP}/fpm/pool.d/www.conf"
"php-fpm${PHP}" -t
systemctl restart "php${PHP}-fpm"

log "Cloudflare address ranges: real client IP for Nginx, web ports in the firewall"
install -m 755 -o root -g root "$HERE/cloudflare-ips.sh" /usr/local/sbin/examis-cloudflare-ips

log "Nginx: HTTPS only, staging password in front of everything"
BASIC_PASSWORD="$(keep_secret BASIC_AUTH_PASSWORD)"
htpasswd -bcB /etc/nginx/examis-staging.htpasswd staging "$BASIC_PASSWORD" 2>/dev/null
chown root:www-data /etc/nginx/examis-staging.htpasswd && chmod 640 /etc/nginx/examis-staging.htpasswd
sed -e "s/__STUDENTS_HOST__/${STUDENTS_HOST}/g" -e "s/__STAFF_HOST__/${STAFF_HOST}/g" \
    "$HERE/nginx/examis-staging.conf" > /etc/nginx/sites-available/examis-staging
ln -sfn /etc/nginx/sites-available/examis-staging /etc/nginx/sites-enabled/examis-staging
rm -f /etc/nginx/sites-enabled/default
# Writes the Cloudflare real-IP list, tests and reloads Nginx, opens 80/443 to Cloudflare.
/usr/local/sbin/examis-cloudflare-ips

log "Environment file (kept if it already exists)"
if [[ ! -f "$APP_ROOT/shared/.env" ]]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    sed -e "s#__STUDENTS_HOST__#${STUDENTS_HOST}#g" -e "s#__STAFF_HOST__#${STAFF_HOST}#g" \
        -e "s#__APP_KEY__#${APP_KEY}#" -e "s#__DB_APP_PASSWORD__#${APP_PASSWORD}#" \
        -e "s#__REDIS_PASSWORD__#${REDIS_PASSWORD}#" \
        "$HERE/env.staging.example" > "$APP_ROOT/shared/.env"
fi
# Only the application reads it; Nginx never needs it.
chown "$APP_USER":"$APP_USER" "$APP_ROOT/shared/.env"
chmod 600 "$APP_ROOT/shared/.env"
grep -q "^DB_DATABASE=${DB_NAME}$" "$APP_ROOT/shared/.env" || fail ".env must point at ${DB_NAME}"

log "Release script"
install -d -m 755 -o "$APP_USER" -g "$APP_USER" "$APP_ROOT/deploy"
install -m 755 -o "$APP_USER" -g "$APP_USER" "$HERE/release.sh" "$APP_ROOT/deploy/release.sh"

log "Queue workers and scheduler (started by the first release)"
install -m 644 "$HERE"/systemd/examis-*.service "$HERE"/systemd/examis-*.timer /etc/systemd/system/
systemctl daemon-reload
systemctl enable examis-queue.service examis-queue-fallback.service examis-scheduler.timer >/dev/null
systemctl enable --now examis-cloudflare-ips.timer >/dev/null

log "Done"
cat <<EOF

Secrets are in ${SECRETS} (root only).
Staging sign-in prompt: user "staging", password = BASIC_AUTH_PASSWORD in that file.
Ports 80 and 443 accept Cloudflare only: open the site through its host names.
Fonts for the printed form: copy times.ttf and timesbd.ttf to ${APP_ROOT}/shared/fonts
(without them the form falls back to DejaVu Sans).
Next: upload a release built by package.ps1 and run release.sh as ${APP_USER}.
EOF
