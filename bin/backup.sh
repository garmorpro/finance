#!/usr/bin/env bash
set -euo pipefail

# Finance app backup: dumps the database and archives storage/attachments
# + storage/imports, then encrypts the result with AES-256. Meant to run
# via cron. See docs/backup-and-recovery.md for setup and restore steps.
#
# Refuses to run without BACKUP_ENCRYPTION_PASSPHRASE set — CLAUDE.md
# requires backups be encrypted when possible, so this writes nothing to
# disk unencrypted rather than silently degrading.

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

if [ -f .env ]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

: "${DB_HOST:?DB_HOST is not set (check .env)}"
: "${DB_DATABASE:?DB_DATABASE is not set (check .env)}"
: "${DB_USERNAME:?DB_USERNAME is not set (check .env)}"
: "${BACKUP_ENCRYPTION_PASSPHRASE:?BACKUP_ENCRYPTION_PASSPHRASE is not set. Backups are refused unencrypted — see docs/backup-and-recovery.md.}"

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_ROOT/storage/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
TIMESTAMP="$(date -u +%Y%m%d-%H%M%S)"
WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT

mkdir -p "$BACKUP_DIR"

echo "Dumping database ${DB_DATABASE}..."
MYSQL_PWD="${DB_PASSWORD:-}" mysqldump \
    --host="${DB_HOST}" \
    --port="${DB_PORT:-3306}" \
    --user="${DB_USERNAME}" \
    --single-transaction \
    --routines \
    --triggers \
    "${DB_DATABASE}" > "${WORK_DIR}/database.sql"

echo "Archiving storage/attachments and storage/imports..."
tar -czf "${WORK_DIR}/storage.tar.gz" \
    -C "$PROJECT_ROOT" \
    storage/attachments storage/imports 2>/dev/null || true

ARCHIVE="${WORK_DIR}/finance-backup-${TIMESTAMP}.tar"
tar -cf "$ARCHIVE" -C "$WORK_DIR" database.sql storage.tar.gz

ENCRYPTED_FILE="${BACKUP_DIR}/finance-backup-${TIMESTAMP}.tar.enc"
openssl enc -aes-256-cbc -pbkdf2 -salt \
    -pass "pass:${BACKUP_ENCRYPTION_PASSPHRASE}" \
    -in "$ARCHIVE" \
    -out "$ENCRYPTED_FILE"

chmod 600 "$ENCRYPTED_FILE"

echo "Backup written to ${ENCRYPTED_FILE}"

echo "Pruning backups older than ${RETENTION_DAYS} days..."
find "$BACKUP_DIR" -name 'finance-backup-*.tar.enc' -mtime "+${RETENTION_DAYS}" -delete

echo "Done."
