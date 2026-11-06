#!/usr/bin/env bash
# EXAMIS perf environment on the staging host (gate G2). Run as root:
#
#   bash provision-perf.sh
#
# Builds an isolated copy of the application for load tests, reachable only on
# 127.0.0.1:8443 (students.perf.localhost, staff.perf.localhost):
#   - its own PostgreSQL 16 cluster "perf" on 127.0.0.1:5433 (database
#     examis_perf, marked synthetic); its roles do not exist in the staging
#     cluster, so they cannot reach examis_staging
#   - its own PHP-FPM master, Redis instance and queue worker, run by the user
#     "examisperf", grouped in examis-perf.target, which is started only for a
#     test session (not at boot)
#   - k6 (official arm64 binary, checksum verified), run by the user "qe"
#     under CPU and memory caps (qe-run.sh)
# The staging site, its pool, database, Redis and cluster are not changed;
# Nginx is reloaded once to add the loopback vhost. Safe to run again.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
PERF=/srv/examis-perf
ETC=/etc/examis-perf
PG_VERSION=16
PG_PORT=5433
K6_VERSION=2.2.0

log() { printf '\n[perf] %s\n' "$*"; }
fail() { printf '[perf] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -u)" == "0" ]] || fail "run as root"
[[ "$(uname -m)" == "aarch64" ]] || fail "expected the ARM64 staging host"

log "Users"
id examisperf >/dev/null 2>&1 || useradd --system --home-dir "$PERF" --no-create-home --shell /usr/sbin/nologin examisperf
id qe >/dev/null 2>&1 || useradd --system --home-dir "$PERF/qe" --no-create-home --shell /usr/sbin/nologin qe

log "Directories"
install -d -m 750 -o examisperf -g www-data "$PERF"
install -d -m 770 -o examisperf -g www-data "$PERF/storage" "$PERF/storage/logs" "$PERF/storage/app" \
    "$PERF/storage/app/private" "$PERF/storage/app/public" "$PERF/storage/framework" \
    "$PERF/storage/framework/cache" "$PERF/storage/framework/sessions" "$PERF/storage/framework/views"
install -d -m 750 -o examisperf -g examisperf "$PERF/.composer"
install -d -m 750 -o examisperf -g www-data "$PERF/fonts"
cp -n /srv/examis/shared/fonts/* "$PERF/fonts/" 2>/dev/null || true
chown examisperf:www-data "$PERF"/fonts/* 2>/dev/null || true
install -d -m 750 -o qe -g qe "$PERF/qe" "$PERF/qe/results"
install -d -m 750 -o root -g root "$ETC"

log "Secrets (generated once, root only, never printed)"
if [[ ! -f "$ETC/secrets" ]]; then
    install -m 600 -o root -g root /dev/null "$ETC/secrets"
    {
        printf 'APP_KEY=base64:%s\n' "$(openssl rand -base64 32)"
        printf 'DB_APP_PASSWORD=%s\n' "$(openssl rand -hex 24)"
        printf 'DB_OWNER_PASSWORD=%s\n' "$(openssl rand -hex 24)"
        printf 'REDIS_PASSWORD=%s\n' "$(openssl rand -hex 24)"
        printf 'QE_STAFF_PASSWORD=Qe-%s\n' "$(openssl rand -hex 12)"
    } > "$ETC/secrets"
fi
# shellcheck disable=SC1091
. "$ETC/secrets"

log "PostgreSQL cluster ${PG_VERSION}/perf on 127.0.0.1:${PG_PORT} (not started at boot)"
if ! pg_lsclusters --no-header | awk '{print $2}' | grep -qx perf; then
    pg_createcluster "$PG_VERSION" perf --port "$PG_PORT" --start-conf manual
fi
install -m 644 -o postgres -g postgres "$HERE/postgresql/examis-perf.conf" "/etc/postgresql/$PG_VERSION/perf/conf.d/examis-perf.conf"
systemctl restart "postgresql@${PG_VERSION}-perf"
PSQL=(sudo -u postgres psql -p "$PG_PORT" -v ON_ERROR_STOP=1 -qAtX)
# Passwords go through stdin, not the command line.
"${PSQL[@]}" -d postgres <<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'examis_perf_owner') THEN
        CREATE ROLE examis_perf_owner LOGIN;
    END IF;
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'examis_perf_app') THEN
        CREATE ROLE examis_perf_app LOGIN;
    END IF;
END
\$\$;
ALTER ROLE examis_perf_owner PASSWORD '${DB_OWNER_PASSWORD}';
ALTER ROLE examis_perf_app PASSWORD '${DB_APP_PASSWORD}';
SQL
if [[ "$("${PSQL[@]}" -d postgres -c "select 1 from pg_database where datname = 'examis_perf'")" != "1" ]]; then
    sudo -u postgres createdb -p "$PG_PORT" -O examis_perf_owner examis_perf
fi
"${PSQL[@]}" -d examis_perf <<SQL
COMMENT ON DATABASE examis_perf IS 'external_link:synthetic';
REVOKE CONNECT ON DATABASE examis_perf FROM PUBLIC;
GRANT CONNECT ON DATABASE examis_perf TO examis_perf_owner, examis_perf_app;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
ALTER SCHEMA public OWNER TO examis_perf_owner;
GRANT USAGE ON SCHEMA public TO examis_perf_app;
ALTER DEFAULT PRIVILEGES FOR ROLE examis_perf_owner IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO examis_perf_app;
ALTER DEFAULT PRIVILEGES FOR ROLE examis_perf_owner IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO examis_perf_app;
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
SQL

log "Redis instance on 127.0.0.1:6380"
install -m 640 -o root -g redis /dev/null "$ETC/redis.conf"
sed "s/__REDIS_PASSWORD__/${REDIS_PASSWORD}/" "$HERE/redis/examis-perf-redis.conf" > "$ETC/redis.conf"

log "PHP-FPM master and pool"
install -m 644 "$HERE/php/examis-perf-fpm.conf" "$ETC/php-fpm.conf"
php-fpm8.4 -t --fpm-config "$ETC/php-fpm.conf"

log "systemd units (examis-perf.target is started per session, not at boot)"
for unit in "$HERE"/systemd/*; do
    install -m 644 "$unit" "/etc/systemd/system/$(basename "$unit")"
done
install -d /etc/systemd/system/postgresql@16-perf.service.d
install -m 644 "$HERE/systemd-dropins/postgresql@16-perf.conf" /etc/systemd/system/postgresql@16-perf.service.d/examis-perf.conf
systemctl daemon-reload

log "TLS certificate for the loopback names (self-signed, k6 skips verification)"
if [[ ! -f "$ETC/tls.pem" ]]; then
    openssl req -x509 -newkey ec -pkeyopt ec_paramgen_curve:prime256v1 -nodes -days 825 \
        -subj '/CN=students.perf.localhost' \
        -addext 'subjectAltName=DNS:students.perf.localhost,DNS:staff.perf.localhost' \
        -keyout "$ETC/tls.key" -out "$ETC/tls.pem" 2>/dev/null
    chmod 600 "$ETC/tls.key"
fi

log "Nginx vhost on 127.0.0.1:8443"
install -m 644 "$HERE/nginx/examis-perf.conf" /etc/nginx/sites-available/examis-perf
ln -sfn /etc/nginx/sites-available/examis-perf /etc/nginx/sites-enabled/examis-perf
nginx -t
systemctl reload nginx

log "k6 ${K6_VERSION} (official arm64 binary, checksum verified)"
if [[ "$(k6 version 2>/dev/null | awk '{print $2}')" != "v${K6_VERSION}" ]]; then
    tmp="$(mktemp -d)"
    base="https://github.com/grafana/k6/releases/download/v${K6_VERSION}"
    name="k6-v${K6_VERSION}-linux-arm64.tar.gz"
    curl -fsSL -o "$tmp/$name" "$base/$name"
    curl -fsSL -o "$tmp/checksums.txt" "$base/k6-v${K6_VERSION}-checksums.txt"
    (cd "$tmp" && grep " ${name}\$" checksums.txt | sha256sum -c -)
    tar -xzf "$tmp/$name" -C "$tmp"
    install -m 755 "$tmp/k6-v${K6_VERSION}-linux-arm64/k6" /usr/local/bin/k6
    rm -rf "$tmp"
fi
k6 version

log "Session tools"
install -m 755 "$HERE/qe-run.sh" /usr/local/sbin/examis-qe-run
install -m 755 "$HERE/qe-sample.sh" /usr/local/sbin/examis-qe-sample

log "Done. Next: release-perf.sh, then examis-qe-run."
