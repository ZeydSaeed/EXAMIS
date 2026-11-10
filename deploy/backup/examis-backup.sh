#!/usr/bin/env bash
# EXAMIS backup, run as root by examis-backup@<kind>.service:
#
#   examis-backup.sh 6h      # database only (every 6 hours)
#   examis-backup.sh daily   # database, applicant files, .env and .db-owner
#   examis-backup.sh manual  # as daily, asked for from the staff Backups page
#   examis-backup.sh delete   # removes the sets the Backups page asked to delete
#
# Everything is encrypted with age to the recipients in RECIPIENTS before it
# touches the disk; the private key never lives on this server. Kept here:
# 6-hourly sets for 48 hours, 7 daily sets, 4 weekly sets (Sunday's daily).
# With an rclone remote "examis-offsite" configured, each set is also copied
# off the server (the bucket's own lock rule keeps it from being deleted).
set -euo pipefail
umask 077

KIND="${1:?usage: examis-backup.sh 6h|daily|manual|delete}"
# The overrides are for a trial run before installation (no pull group, no
# off-server copy, another directory).
ROOT="${EXAMIS_BACKUP_ROOT:-/var/backups/examis}"
RECIPIENTS="${EXAMIS_BACKUP_RECIPIENTS:-/etc/examis-backup/recipients.txt}"
SHARED=/srv/examis/shared
# Shared with the application (root:examis 1770): the staff Backups page reads
# status.json, asks for a manual backup by creating "request" and for a
# deletion by creating "delete-<kind>-<set>".
STATUS_DIR="${EXAMIS_BACKUP_STATUS_DIR:-$SHARED/storage/app/backup-status}"
ALLOWED_DATABASES=(examis_staging examis_production)
PULL_GROUP="${EXAMIS_BACKUP_PULL_GROUP-examis-pull}"
RCLONE_CONFIG="${EXAMIS_BACKUP_RCLONE_CONFIG:-/root/.config/rclone/rclone.conf}"
OFFSITE="examis-offsite:examis-staging-backups"
ALERTS_ENV="${EXAMIS_BACKUP_ALERTS_ENV:-/etc/examis-alerts/healthchecks.env}"

log() { printf '[backup %s] %s\n' "$KIND" "$*"; }
fail() { printf '[backup %s] ERROR: %s\n' "$KIND" "$*" >&2; exit 1; }

[[ "$KIND" =~ ^(6h|daily|manual|delete)$ ]] || fail "kind must be 6h, daily, manual or delete"
[[ "$(id -u)" == 0 ]] || fail "run as root"
grep -qE '^age1[0-9a-z]{58}$' "$RECIPIENTS" 2>/dev/null || fail "no age recipient in $RECIPIENTS"

# The database comes from the application's own .env and must be allowed.
DB="$(grep -E '^DB_DATABASE=' "$SHARED/.env" | cut -d= -f2-)"
[[ " ${ALLOWED_DATABASES[*]} " == *" $DB "* ]] || fail "database '$DB' is not allowed"
[[ "$DB" != sis ]] || fail "refusing the live database"

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT="$ROOT/$KIND/$STAMP"

# The staff page shows each kind's sets and the last backup run (kept in
# $ROOT/last-run, so a deletion does not replace it). Written to a new file
# and renamed, so the application never sees half a file and a planted link
# is replaced, not followed.
write_status() {
    [[ -d "$STATUS_DIR" ]] || return 0
    local tmp kind name size first_kind=1 first_set last_kind=null last_stamp=null last_result=null
    if [[ -s "$ROOT/last-run" ]]; then
        read -r last_kind last_stamp last_result < "$ROOT/last-run"
    fi
    tmp="$(mktemp "$STATUS_DIR/.status.XXXXXX")"
    {
        printf '{"updated_at":"%s","last_run":{"kind":"%s","stamp":"%s","result":"%s"},"kinds":{' \
            "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$last_kind" "$last_stamp" "$last_result"
        for kind in 6h daily manual weekly; do
            [[ -d "$ROOT/$kind" ]] || continue
            ((first_kind)) || printf ','
            first_kind=0
            local names=()
            mapfile -t names < <(find "$ROOT/$kind" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | grep -E '^[0-9]{8}T[0-9]{6}Z$' | sort -r)
            printf '"%s":{"count":%d,' "$kind" "${#names[@]}"
            if ((${#names[@]})); then
                printf '"last":"%s","size":%s,' "${names[0]}" "$(du -sb "$ROOT/$kind/${names[0]}" | cut -f1)"
            else
                printf '"last":null,"size":null,'
            fi
            printf '"sets":['
            first_set=1
            for name in "${names[@]}"; do
                size="$(du -sb "$ROOT/$kind/$name" | cut -f1)"
                ((first_set)) || printf ','
                first_set=0
                printf '{"name":"%s","size":%s}' "$name" "$size"
            done
            printf ']}'
        done
        printf '}}\n'
    } > "$tmp"
    chmod 640 "$tmp"
    chgrp examis "$tmp" 2>/dev/null || true
    mv -f "$tmp" "$STATUS_DIR/status.json"
}

# One backup at a time: a manual request during the 6-hourly run waits.
exec 9> "$ROOT/.lock"
flock -w 3600 9 || fail "another backup is still running"

# The scheduled kinds report to Healthchecks.io (deploy/alerts): "start", then
# success or "fail". A backup that never runs sends nothing, and Healthchecks.io
# alerts on that silence too.
PING_URL=""
case "$KIND" in
    6h) PING_URL="$(grep -E '^HC_BACKUP_6H=https://' "$ALERTS_ENV" 2>/dev/null | head -1 | cut -d= -f2-)" ;;
    daily) PING_URL="$(grep -E '^HC_BACKUP_DAILY=https://' "$ALERTS_ENV" 2>/dev/null | head -1 | cut -d= -f2-)" ;;
esac
ping_hc() { [[ -z "$PING_URL" ]] || curl -fsS -m 10 --retry 3 -o /dev/null --data-raw "${2:-}" "${PING_URL}$1" || true; }
ping_hc /start

# Whatever happens, a manual request is answered once (so the path unit does
# not start it again) and the page learns the result.
finish() {
    local code=$?
    [[ "$KIND" != manual ]] || rm -f "$STATUS_DIR/request"
    if [[ "$KIND" != delete ]]; then
        printf '%s %s %s\n' "$KIND" "$STAMP" "$([[ $code == 0 ]] && echo ok || echo failed)" > "$ROOT/last-run"
    fi
    write_status || true
    if [[ $code == 0 ]]; then
        ping_hc "" "$KIND $STAMP ok"
    else
        ping_hc /fail "$KIND $STAMP failed (exit $code); journalctl -u examis-backup@$KIND.service"
    fi
}
trap finish EXIT

# Deletions asked for from the Backups page. Only the request's file name is
# read, and it must name an existing set; the last full set (database and
# files) is always kept.
if [[ "$KIND" == delete ]]; then
    full_sets() { find "$ROOT/daily" "$ROOT/manual" "$ROOT/weekly" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | wc -l; }
    for request in "$STATUS_DIR"/delete-*; do
        [[ -e "$request" ]] || continue
        base="$(basename -- "$request")"
        rm -f -- "$request"
        if [[ ! "$base" =~ ^delete-(6h|daily|manual|weekly)-([0-9]{8}T[0-9]{6}Z)$ ]]; then
            log "Ignored request $base"
        elif [[ ! -d "$ROOT/${BASH_REMATCH[1]}/${BASH_REMATCH[2]}" ]]; then
            log "No set ${BASH_REMATCH[1]}/${BASH_REMATCH[2]}"
        elif [[ "${BASH_REMATCH[1]}" != 6h && "$(full_sets)" -le 1 ]]; then
            log "Kept ${BASH_REMATCH[1]}/${BASH_REMATCH[2]}: the last full set"
        else
            rm -rf -- "${ROOT:?}/${BASH_REMATCH[1]}/${BASH_REMATCH[2]}"
            log "Deleted ${BASH_REMATCH[1]}/${BASH_REMATCH[2]}"
        fi
    done
    exit 0
fi
mkdir -p "$OUT"
# The owner's computer pulls daily sets as examis-pull: it may enter these
# directories and read the encrypted files, nothing else.
[[ -z "$PULL_GROUP" ]] || { chgrp "$PULL_GROUP" "$ROOT" "$ROOT/$KIND" && chmod 750 "$ROOT" "$ROOT/$KIND"; }
encrypt() { age --encrypt --recipients-file "$RECIPIENTS" --output "$1"; }

log "Database $DB"
# pg_restore --list reads the dump on its way to age: a dump whose header and
# table of contents it cannot read fails the backup, without a plain copy on
# disk. tee -p keeps going when pg_restore stops reading early.
mkfifo "$OUT/.dump-fifo"
runuser -u postgres -- pg_restore --list < "$OUT/.dump-fifo" > "$OUT/.toc" 2>/dev/null &
LIST_PID=$!
runuser -u postgres -- pg_dump --format=custom --no-owner --dbname="$DB" \
    | tee -p "$OUT/.dump-fifo" \
    | encrypt "$OUT/database.dump.age"
wait "$LIST_PID" || fail "pg_restore could not read the dump"
[[ -s "$OUT/.toc" ]] || fail "pg_restore listed nothing"
grep -c ' TABLE DATA ' "$OUT/.toc" > "$OUT/table-data-entries.txt"
rm -f "$OUT/.toc" "$OUT/.dump-fifo"
runuser -u postgres -- psql --dbname="$DB" -AtF' ' -c "
    select format('%s %s', t.relname, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', n.nspname, t.relname), false, true, '')))[1]::text)
    from pg_class t join pg_namespace n on n.oid = t.relnamespace
    where t.relkind = 'r' and n.nspname = 'public' order by 1" > "$OUT/row-counts.txt"

if [[ "$KIND" == daily || "$KIND" == manual ]]; then
    log "Applicant files"
    (cd "$SHARED/storage/app" && find . -type f -print0 | sort -z | xargs -0 -r sha256sum) | encrypt "$OUT/files.sha256.age"
    tar -C "$SHARED/storage" -cf - app | encrypt "$OUT/files.tar.age"
    find "$SHARED/storage/app" -type f | wc -l > "$OUT/file-count.txt"

    log ".env and .db-owner"
    tar -C "$SHARED" -cf - .env .db-owner | encrypt "$OUT/secrets.tar.age"
fi

(cd "$OUT" && sha256sum -- * > SHA256SUMS)
if [[ -n "$PULL_GROUP" ]]; then chgrp -R "$PULL_GROUP" "$OUT" && chmod 750 "$OUT" && chmod 640 "$OUT"/*; fi
printf '%s %s %s\n' "$STAMP" "$KIND" "$(du -sb "$OUT" | cut -f1)" > "$ROOT/last-$KIND"

if [[ "$KIND" == daily && "$(date -u +%u)" == 7 ]]; then
    log "Weekly copy"
    mkdir -p "$ROOT/weekly" && cp -al "$OUT" "$ROOT/weekly/$STAMP"
fi

log "Retention"
prune() { find "$ROOT/$1" -mindepth 1 -maxdepth 1 -type d | sort | head -n -"$2" | xargs -r rm -rf --; }
[[ -d "$ROOT/6h" ]] && find "$ROOT/6h" -mindepth 1 -maxdepth 1 -type d -mmin +$((48 * 60)) -exec rm -rf -- {} +
[[ -d "$ROOT/daily" ]] && prune daily 7
[[ -d "$ROOT/weekly" ]] && prune weekly 4
[[ -d "$ROOT/manual" ]] && prune manual 10

# Off-server last, after the local set is complete and pruned. The whole kind
# directory is copied, so sets missed during an outage go up with the next
# run; --immutable never rewrites an object already there. A failure fails the
# unit (visible in systemctl --failed) but keeps the local set.
if [[ -s "$RCLONE_CONFIG" ]] && rclone --config "$RCLONE_CONFIG" listremotes | grep -qx 'examis-offsite:'; then
    log "Off-server copy"
    rclone --config "$RCLONE_CONFIG" copy --immutable --retries 5 --low-level-retries 10 \
        "$ROOT/$KIND" "$OFFSITE/$KIND" || fail "off-server copy failed; the local set $OUT is kept"
    printf '%s\n' "$STAMP" > "$ROOT/last-offsite-$KIND"
else
    log "No off-server remote configured: kept on this server only"
fi

log "Done: $OUT ($(du -sh "$OUT" | cut -f1))"
