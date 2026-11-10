#!/usr/bin/env bash
# One-way live copy of examis_production to the owner's PC (PostgreSQL
# logical replication). Run as root on the server, once:
#
#   bash server-setup.sh <tunnel-key.pub>
#
# - wal_level=logical (restarts PostgreSQL once: a few seconds offline), and
#   max_slot_wal_keep_size so a PC left off can never fill the disk; past
#   that the PC copy is rebuilt with pc-setup.ps1.
# - Role examis_replica: read-only, may replicate; password in
#   /root/examis-replica.env (root 600), never printed.
# - Publication examis_pub: application tables only. Left out: queue, cache,
#   sessions and reset tokens, sign-in secrets (passkeys, trusted devices,
#   invitations) and the secret columns of users.
# - Account examis-tunnel: its key may only forward to 127.0.0.1:5432.
# Safe to run again; the restart happens only when wal_level changes.
set -euo pipefail
KEY="${1:?usage: server-setup.sh <tunnel-key.pub>}"
DB=examis_production
log() { printf '\n[replica] %s\n' "$*"; }
fail() { printf '[replica] ERROR: %s\n' "$*" >&2; exit 1; }
psql_db() { runuser -u postgres -- psql -v ON_ERROR_STOP=1 -d "$DB" -Atq "$@"; }

[[ "$(id -u)" == 0 ]] || fail "run as root"
grep -qE '^ssh-ed25519 [A-Za-z0-9+/=]+( .*)?$' "$KEY" || fail "$KEY is not one ssh-ed25519 public key"
grep -qx "DB_DATABASE=$DB" /srv/examis/shared/.env || fail "the application does not use $DB"

log "Settings"
runuser -u postgres -- psql -v ON_ERROR_STOP=1 -Atq -c "ALTER SYSTEM SET max_slot_wal_keep_size = '4GB'"
if [[ "$(runuser -u postgres -- psql -Atc 'show wal_level')" != logical ]]; then
    runuser -u postgres -- psql -v ON_ERROR_STOP=1 -Atq -c "ALTER SYSTEM SET wal_level = 'logical'"
    log "Restarting PostgreSQL"
    systemctl restart postgresql
    for _ in $(seq 1 30); do runuser -u postgres -- pg_isready -q && break; sleep 1; done
    systemctl restart examis-queue.service 2>/dev/null || true
else
    runuser -u postgres -- psql -Atq -c 'select pg_reload_conf()' > /dev/null
fi
runuser -u postgres -- psql -Atc "select 'wal_level=' || current_setting('wal_level') || ' max_slot_wal_keep_size=' || current_setting('max_slot_wal_keep_size')"

log "Role examis_replica"
if [[ ! -s /root/examis-replica.env ]]; then
    umask 077
    printf 'REPLICA_PASSWORD=%s\n' "$(openssl rand -hex 24)" > /root/examis-replica.env
fi
# shellcheck disable=SC1091
. /root/examis-replica.env
OWNER="$(psql_db -c "select pg_get_userbyid(relowner) from pg_class where relname = 'users' and relkind = 'r'")"
psql_db -v pw="$REPLICA_PASSWORD" -v owner="$OWNER" <<'SQL'
SELECT format('CREATE ROLE examis_replica LOGIN REPLICATION PASSWORD %L', :'pw')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'examis_replica') \gexec
SELECT format('ALTER ROLE examis_replica LOGIN REPLICATION PASSWORD %L', :'pw') \gexec
GRANT CONNECT ON DATABASE examis_production TO examis_replica;
GRANT USAGE ON SCHEMA public TO examis_replica;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO examis_replica;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO examis_replica;
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public GRANT SELECT ON TABLES TO examis_replica', :'owner') \gexec
SELECT format('ALTER DEFAULT PRIVILEGES FOR ROLE %I IN SCHEMA public GRANT SELECT ON SEQUENCES TO examis_replica', :'owner') \gexec
SQL

log "Publication examis_pub"
psql_db <<'SQL'
SELECT string_agg(
         CASE WHEN c.relname = 'users' THEN
           format('%I (%s)', c.relname, (
             SELECT string_agg(quote_ident(a.attname), ', ' ORDER BY a.attnum)
             FROM pg_attribute a
             WHERE a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
               AND a.attname NOT IN ('password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'access_code')))
         ELSE quote_ident(c.relname) END, ', ' ORDER BY c.relname) AS tables
FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname = 'public' AND c.relkind = 'r'
  AND c.relname NOT IN ('migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
                        'sessions', 'staff_sessions', 'password_reset_tokens',
                        'passkeys', 'trusted_devices', 'staff_invitations') \gset
SELECT CASE WHEN EXISTS (SELECT FROM pg_publication WHERE pubname = 'examis_pub')
            THEN format('ALTER PUBLICATION examis_pub SET TABLE %s', :'tables')
            ELSE format('CREATE PUBLICATION examis_pub FOR TABLE %s', :'tables') END \gexec
SQL
psql_db -c "select count(*) || ' tables published' from pg_publication_tables where pubname = 'examis_pub'"

log "Tunnel account examis-tunnel"
id examis-tunnel > /dev/null 2>&1 || useradd --system --create-home --home-dir /var/lib/examis-tunnel --shell /usr/sbin/nologin examis-tunnel
passwd -l examis-tunnel > /dev/null
install -d -m 700 -o examis-tunnel -g examis-tunnel /var/lib/examis-tunnel/.ssh
printf 'restrict,port-forwarding,permitopen="127.0.0.1:5432",command="/bin/false" %s\n' "$(cat "$KEY")" \
    > /var/lib/examis-tunnel/.ssh/authorized_keys
chown examis-tunnel:examis-tunnel /var/lib/examis-tunnel/.ssh/authorized_keys
chmod 600 /var/lib/examis-tunnel/.ssh/authorized_keys

log "Done"
