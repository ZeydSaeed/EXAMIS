#!/usr/bin/env bash
# Second half of restore-production.ps1, run on the server as "examis":
#
#   bash restore-on-server.sh <work dir>      # holds database.dump [files.tar]
#
# Replaces every row of the live database with the backup's rows in ONE
# transaction (all or nothing): TRUNCATE all tables but "migrations", then
# the backup's data. The schema stays the current one, so a backup from an
# older release that no longer fits fails and changes nothing. The site is
# in maintenance mode meanwhile and comes back whatever happens. With
# files.tar, applicant files are written back over storage/app too.
set -euo pipefail
umask 077
WORK="${1:?usage: restore-on-server.sh <work dir>}"
SHARED=/srv/examis/shared
APP=/srv/examis/current
log() { printf '[restore] %s\n' "$*"; }
fail() { printf '[restore] ERROR: %s\n' "$*" >&2; exit 1; }

[[ "$(id -un)" == examis ]] || fail "run as examis"
[[ "$WORK" =~ ^/tmp/examis-restore-[0-9]{14}$ ]] || fail "work dir must be /tmp/examis-restore-<stamp>"
[[ -s "$WORK/database.dump" ]] || fail "no $WORK/database.dump"
DB="$(grep -E '^DB_DATABASE=' "$SHARED/.env" | cut -d= -f2-)"
[[ "$DB" == examis_production || "$DB" == examis_staging ]] || fail "database '$DB' is not allowed"
OWNER="$(grep -E '^DB_USERNAME=' "$SHARED/.db-owner" | cut -d= -f2-)"
PGPASSWORD="$(grep -E '^DB_PASSWORD=' "$SHARED/.db-owner" | cut -d= -f2-)"
export PGPASSWORD
psql_owner() { psql -h 127.0.0.1 -U "$OWNER" -d "$DB" -v ON_ERROR_STOP=1 "$@"; }

finish() {
    local code=$?
    (cd "$APP" && php artisan up > /dev/null) || true
    rm -rf -- "$WORK"
    if [[ $code == 0 ]]; then log "Site is back"; else log "FAILED: nothing was changed in the database; the site is back"; fi
}
trap finish EXIT

log "Maintenance mode"
(cd "$APP" && php artisan down --retry=30 > /dev/null)

# Every table's data and sequence position from the backup, except the
# migration list, which belongs to the current schema. pg_restore follows the
# list's order, so tables go parents first (foreign keys checked as rows go
# in), then the sequence positions.
pg_restore -l "$WORK/database.dump" > "$WORK/full.list"
: > "$WORK/restore.list"
while read -r table; do
    [[ "$table" == migrations ]] && continue
    grep -E " TABLE DATA public ${table} " "$WORK/full.list" >> "$WORK/restore.list" || true
done < <(psql_owner -Atc "with recursive fk as ( select ch.relname::text as child, pa.relname::text as parent from pg_constraint k join pg_class ch on ch.oid = k.conrelid join pg_class pa on pa.oid = k.confrelid where k.contype = 'f' and k.conrelid <> k.confrelid and ch.relnamespace = 'public'::regnamespace ), depth(t, d) as ( select relname::text, 0 from pg_class where relkind = 'r' and relnamespace = 'public'::regnamespace union all select fk.child, depth.d + 1 from fk join depth on fk.parent = depth.t where depth.d < 50 ) select t from depth group by t order by max(d), t")
grep -E ' SEQUENCE SET public ' "$WORK/full.list" | grep -v ' public migrations_id_seq ' >> "$WORK/restore.list" || true
# A backup table missing from the current schema would otherwise be skipped
# without a word.
wanted="$(grep -E ' TABLE DATA public ' "$WORK/full.list" | grep -vc ' TABLE DATA public migrations ' || true)"
listed="$(grep -c ' TABLE DATA public ' "$WORK/restore.list" || true)"
[[ "$wanted" -gt 0 && "$listed" == "$wanted" ]] || fail "only $listed of $wanted backup tables fit the current schema; nothing was changed"
TABLES="$(psql_owner -Atc "select string_agg(format('public.%I', tablename), ', ') from pg_tables where schemaname = 'public' and tablename <> 'migrations'")"

# The whole SQL is written out first: a dump that breaks off part way must
# fail here, not reach psql as a shorter script that still commits.
{
    echo "SET lock_timeout = '30s';"
    echo "TRUNCATE $TABLES RESTART IDENTITY CASCADE;"
} > "$WORK/data.sql"
pg_restore --data-only --no-owner --no-privileges -L "$WORK/restore.list" -f - "$WORK/database.dump" >> "$WORK/data.sql" \
    || fail "the backup could not be read"

log "Database $DB: replacing all rows (one transaction)"
psql_owner -q -1 -f "$WORK/data.sql" > /dev/null

if [[ -s "$WORK/files.tar" ]]; then
    log "Applicant files"
    tar -xf "$WORK/files.tar" -C "$SHARED/storage" --exclude='app/backup-status' --exclude='app/backup-status/*'
fi

(cd "$APP" && php artisan cache:clear > /dev/null && php artisan queue:restart > /dev/null)

log "Row counts now"
psql_owner -Atc "
    select format('%s %s', t.relname, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', n.nspname, t.relname), false, true, '')))[1]::text)
    from pg_class t join pg_namespace n on n.oid = t.relnamespace
    where t.relkind = 'r' and n.nspname = 'public' order by 1"
