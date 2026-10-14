#!/usr/bin/env bash
# EXAMIS restore drill — restores a backup into an ISOLATED database and
# documents directory, then verifies it with the application's own checks.
# It refuses to target a database whose name does not end in _restore_drill.
#
# Usage:
#   BACKUP=/backups/20261014T020000Z \
#   DRILL_DB=examis_restore_drill DRILL_DOCUMENTS=/srv/drill/documents \
#   PGHOST=... PGUSER=... PGPASSWORD=... \
#   scripts/ops/restore-drill.sh
#
# Writes a timestamped report to storage/restore-drills/ (no secrets, no personal data).
set -euo pipefail

: "${BACKUP:?}" "${DRILL_DB:?}" "${DRILL_DOCUMENTS:?}"

if [[ "${DRILL_DB}" != *_restore_drill ]]; then
    echo "Refusing: DRILL_DB must end with _restore_drill (got ${DRILL_DB})." >&2
    exit 2
fi

started_epoch="$(date -u +%s)"
report_dir="$(dirname "$0")/../../storage/restore-drills"
mkdir -p "${report_dir}"
report="${report_dir}/$(date -u +%Y%m%dT%H%M%SZ).md"
work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT

log() { echo "$*" | tee -a "${report}"; }

log "# Restore drill $(date -u +%FT%TZ)"
log ""
log "- Backup: \`$(basename "${BACKUP}")\`"
grep '^created_at=' "${BACKUP}/COUNTS.txt" | sed 's/^/- Backup /' | tee -a "${report}"

log "## 1. Integrity of the backup artifacts"
for file in "${BACKUP}"/*.gpg; do
    gpg --batch --quiet --decrypt --output "${work}/$(basename "${file}" .gpg)" "${file}"
done
(cd "${work}" && sha256sum --check --quiet "${BACKUP}/MANIFEST.sha256") && log "- Checksums: OK"

log "## 2. Restore database into ${DRILL_DB}"
dropdb --if-exists "${DRILL_DB}"
createdb "${DRILL_DB}"
pg_restore --no-owner --no-privileges --exit-on-error --dbname="${DRILL_DB}" "${work}/database.dump"
log "- pg_restore: OK"

log "## 3. Restore documents into ${DRILL_DOCUMENTS}"
rm -rf "${DRILL_DOCUMENTS}" && mkdir -p "${DRILL_DOCUMENTS}"
if [[ -f "${work}/documents.tar.gz" ]]; then
    tar --force-local --extract --gzip --file="${work}/documents.tar.gz" --directory="${DRILL_DOCUMENTS}"
    log "- Documents extracted: $(find "${DRILL_DOCUMENTS}" -type f | wc -l) files"
else
    log "- No documents archive in this backup (object-storage versioning in use); restore the bucket snapshot separately."
fi

log "## 4. Compare counts with the backup manifest"
for metric in applications submitted documents; do
    expected="$(grep "^${metric}=" "${BACKUP}/COUNTS.txt" | cut -d= -f2)"
    case "${metric}" in
        applications) sql='select count(*) from applications' ;;
        submitted) sql='select count(*) from applications where reference_number is not null' ;;
        documents) sql='select count(*) from application_documents' ;;
    esac
    actual="$(psql --dbname="${DRILL_DB}" -Atc "${sql}")"
    status=$([[ "${expected}" == "${actual}" ]] && echo OK || echo MISMATCH)
    log "- ${metric}: backup=${expected} restored=${actual} ${status}"
done

log "## 5. Application-level verification"
export DB_CONNECTION=pgsql DB_HOST="${PGHOST:-127.0.0.1}" DB_PORT="${PGPORT:-5432}" DB_USERNAME="${PGUSER:-}" DB_PASSWORD="${PGPASSWORD:-}" \
    DB_DATABASE="${DRILL_DB}" DB_URL='' DOCUMENTS_DISK=documents DOCUMENTS_ROOT="${DRILL_DOCUMENTS}" APP_ENV=restore-drill
php artisan migrate:status --no-interaction > /dev/null && log "- Schema matches migrations: OK"
if php artisan examis:verify-integrity --hashes --no-interaction | tee -a "${report}"; then
    log "- Integrity: OK"
else
    log "- Integrity: FAILED"
fi

finished_epoch="$(date -u +%s)"
log ""
log "## Result"
log "- Duration (RTO evidence for this data size): $(( (finished_epoch - started_epoch) / 60 )) min"
log "- Manual follow-up (record below): staff login, open a restored application, download a document, issue a receipt, submit a test application, then drop ${DRILL_DB}."
echo "Report: ${report}"
