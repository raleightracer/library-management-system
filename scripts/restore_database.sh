#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_FILE="${1:-}"

if [[ -z "$BACKUP_FILE" ]]; then
  echo "Usage: bash scripts/restore_database.sh path/to/backup.sql.gz" >&2
  exit 1
fi

if [[ ! -f "$BACKUP_FILE" ]]; then
  echo "Backup file not found: $BACKUP_FILE" >&2
  exit 1
fi

if [[ -f "$ROOT_DIR/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$ROOT_DIR/.env"
  set +a
fi

DB_HOST="${SCHODEX_DB_HOST:-localhost}"
DB_PORT="${SCHODEX_DB_PORT:-3306}"
DB_NAME="${SCHODEX_DB_NAME:-quadbyte_lms}"
DB_USER="${SCHODEX_DB_USER:-root}"
DB_PASSWORD="${SCHODEX_DB_PASSWORD:-}"
DB_SOCKET="${SCHODEX_DB_SOCKET:-/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock}"

MYSQL_ARGS=(
  --host="$DB_HOST"
  --port="$DB_PORT"
  --user="$DB_USER"
)

if [[ "$DB_HOST" == "localhost" && -S "$DB_SOCKET" ]]; then
  MYSQL_ARGS+=(--socket="$DB_SOCKET")
fi

case "$BACKUP_FILE" in
  *.sql.gz)
    gunzip -c "$BACKUP_FILE" | MYSQL_PWD="$DB_PASSWORD" mysql \
      "${MYSQL_ARGS[@]}" \
      --default-character-set=utf8mb4 \
      "$DB_NAME"
    ;;
  *.sql)
    MYSQL_PWD="$DB_PASSWORD" mysql \
      "${MYSQL_ARGS[@]}" \
      --default-character-set=utf8mb4 \
      "$DB_NAME" < "$BACKUP_FILE"
    ;;
  *)
    echo "Unsupported backup extension. Use .sql or .sql.gz." >&2
    exit 1
    ;;
esac

echo "Restored $BACKUP_FILE into $DB_NAME."
