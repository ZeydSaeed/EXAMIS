#!/usr/bin/env bash
# EXAMIS logical backup: PostgreSQL dump + document storage snapshot,
# encrypted, with a manifest of checksums. Intended for the database host
# or a backup runner; PITR (WAL archiving) is configured separately
# (docs/operations/BACKUP-RESTORE.md).
#
# Required environment (from the secret store, never committed):
#   PGHOST PGPORT PGUSER PGDATABASE PGPASSWORD   backup role (read-only + pg_dump rights)
#   DOCUMENTS_PATH                               local documents root, or empty when using object storage versioning
#   BACKUP_DIR                                   where artifacts are written before off-site copy
#   BACKUP_GPG_RECIPIENT                         public key id used to encrypt artifacts
set -euo pipefail

: "${PGDATABASE:?}" "${BACKUP_DIR:?}" "${BACKUP_GPG_RECIPIENT:?}"

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
target="${BACKUP_DIR}/${stamp}"
mkdir -p "${target}"
umask 077

echo "[backup] dumping database ${PGDATABASE}"
pg_dump --format=custom --compress=6 --no-owner --no-privileges --file="${target}/database.dump"
pg_restore --list "${target}/database.dump" > /dev/null   # fails fast on a corrupt archive

if [[ -n "${DOCUMENTS_PATH:-}" ]]; then
    echo "[backup] archiving documents from ${DOCUMENTS_PATH}"
    tar --force-local --create --gzip --file="${target}/documents.tar.gz" --directory="${DOCUMENTS_PATH}" .
fi

echo "[backup] writing manifest"
(
    cd "${target}"
    sha256sum ./* > MANIFEST.sha256
    {
        echo "created_at=${stamp}"
        echo "database=${PGDATABASE}"
        echo "pg_dump_version=$(pg_dump --version)"
        echo "applications=$(psql -Atc 'select count(*) from applications')"
        echo "submitted=$(psql -Atc 'select count(*) from applications where reference_number is not null')"
        echo "documents=$(psql -Atc 'select count(*) from application_documents')"
    } > COUNTS.txt
)

echo "[backup] encrypting"
for file in "${target}"/database.dump "${target}"/documents.tar.gz; do
    [[ -f "${file}" ]] || continue
    gpg --batch --yes --encrypt --recipient "${BACKUP_GPG_RECIPIENT}" --output "${file}.gpg" "${file}"
    shred -u "${file}" 2>/dev/null || rm -f "${file}"
done

echo "[backup] done: ${target}"
echo "[backup] next: copy ${target} off-site (e.g. rclone/aws s3 cp with object lock) and record the job result."
