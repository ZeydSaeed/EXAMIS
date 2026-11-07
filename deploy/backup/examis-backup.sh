#!/usr/bin/env bash
# EXAMIS staging backup, run as root by examis-backup@<kind>.service:
#
#   examis-backup.sh 6h      # database only (every 6 hours)
#   examis-backup.sh daily   # database, applicant files, .env and .db-owner
#
# Everything is encrypted with age to the recipients in RECIPIENTS before it
# touches the disk; the private key never lives on this server. Kept here:
# 6-hourly sets for 48 hours, 7 daily sets, 4 weekly sets (Sunday's daily).
# With an rclone remote "examis-offsite" configured, each set is also copied
# off the server (the bucket's own lock rule keeps it from being deleted).
set -euo pipefail
umask 077

KIND="${1:?usage: examis-backup.sh 6h|daily}"
ROOT=/var/backups/examis
RECIPIENTS=/etc/examis-backup/recipients.txt
SHARED=/srv/examis/shared
ALLOWED_DATABASES=(examis_staging)
PULL_GROUP=examis-pull
RCLONE_CONFIG=/root/.config/rclone/rclone.conf

log() { printf '[backup %s] %s\n' "$KIND" "$*"; }
fail() { printf '[backup %s] ERROR: %s\n' "$KIND" "$*" >&2; exit 1; }

[[ "$KIND" == 6h || "$KIND" == daily ]] || fail "kind must be 6h or daily"
[[ "$(id -u)" == 0 ]] || fail "run as root"
grep -qE '^age1[0-9a-z]{58}$' "$RECIPIENTS" 2>/dev/null || fail "no age recipient in $RECIPIENTS"

# The database comes from the application's own .env and must be allowed.
DB="$(grep -E '^DB_DATABASE=' "$SHARED/.env" | cut -d= -f2-)"
[[ " ${ALLOWED_DATABASES[*]} " == *" $DB "* ]] || fail "database '$DB' is not allowed"
[[ "$DB" != sis ]] || fail "refusing the live database"

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT="$ROOT/$KIND/$STAMP"
mkdir -p "$OUT"
# The owner's computer pulls daily sets as examis-pull: it may enter these
# directories and read the encrypted files, nothing else.
chgrp "$PULL_GROUP" "$ROOT" "$ROOT/$KIND" && chmod 750 "$ROOT" "$ROOT/$KIND"
encrypt() { age --encrypt --recipients-file "$RECIPIENTS" --output "$1"; }

log "Database $DB"
# pg_restore --list reads the dump on its way to age: a dump whose header and
# table of contents it cannot read fails the backup, without a plain copy on
# disk. tee -p keeps going when pg_restore stops reading early.
runuser -u postgres -- pg_dump --format=custom --no-owner --dbname="$DB" \
    | tee -p >(runuser -u postgres -- pg_restore --list > "$OUT/.toc" 2>/dev/null || : > "$OUT/.toc-failed") \
    | encrypt "$OUT/database.dump.age"
wait $! 2>/dev/null || true
[[ ! -e "$OUT/.toc-failed" && -s "$OUT/.toc" ]] || fail "pg_restore could not read the dump"
grep -c ' TABLE DATA ' "$OUT/.toc" > "$OUT/table-data-entries.txt"
rm -f "$OUT/.toc"
runuser -u postgres -- psql --dbname="$DB" -AtF' ' -c "
    select format('%s %s', t.relname, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', n.nspname, t.relname), false, true, '')))[1]::text)
    from pg_class t join pg_namespace n on n.oid = t.relnamespace
    where t.relkind = 'r' and n.nspname = 'public' order by 1" > "$OUT/row-counts.txt"

if [[ "$KIND" == daily ]]; then
    log "Applicant files"
    (cd "$SHARED/storage/app" && find . -type f -print0 | sort -z | xargs -0 -r sha256sum) | encrypt "$OUT/files.sha256.age"
    tar -C "$SHARED/storage" -cf - app | encrypt "$OUT/files.tar.age"
    find "$SHARED/storage/app" -type f | wc -l > "$OUT/file-count.txt"

    log ".env and .db-owner"
    tar -C "$SHARED" -cf - .env .db-owner | encrypt "$OUT/secrets.tar.age"
fi

(cd "$OUT" && sha256sum -- * > SHA256SUMS)
chgrp -R "$PULL_GROUP" "$OUT" && chmod 750 "$OUT" && chmod 640 "$OUT"/*
printf '%s %s %s\n' "$STAMP" "$KIND" "$(du -sb "$OUT" | cut -f1)" > "$ROOT/last-$KIND"

if [[ "$KIND" == daily && "$(date -u +%u)" == 7 ]]; then
    log "Weekly copy"
    mkdir -p "$ROOT/weekly" && cp -al "$OUT" "$ROOT/weekly/$STAMP"
fi

if [[ -s "$RCLONE_CONFIG" ]] && rclone --config "$RCLONE_CONFIG" listremotes | grep -qx 'examis-offsite:'; then
    log "Off-server copy"
    rclone --config "$RCLONE_CONFIG" copy --immutable "$OUT" "examis-offsite:$KIND/$STAMP"
    printf '%s\n' "$STAMP" > "$ROOT/last-offsite-$KIND"
else
    log "No off-server remote configured: kept on this server only"
fi

log "Retention"
prune() { find "$ROOT/$1" -mindepth 1 -maxdepth 1 -type d | sort | head -n -"$2" | xargs -r rm -rf --; }
find "$ROOT/6h" -mindepth 1 -maxdepth 1 -type d -mmin +$((48 * 60)) -exec rm -rf -- {} +
prune daily 7
[[ -d "$ROOT/weekly" ]] && prune weekly 4

log "Done: $OUT ($(du -sh "$OUT" | cut -f1))"
