#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

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
BACKUP_DIR="${BACKUP_DIR:-$ROOT_DIR/backups}"
INCLUDE_ROUTINES="${SCHODEX_BACKUP_ROUTINES:-false}"
STAMP="$(date +%Y%m%d_%H%M%S)"
OUTPUT="$BACKUP_DIR/schodex_lms_${STAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

MYSQL_ARGS=(
  --host="$DB_HOST"
  --port="$DB_PORT"
  --user="$DB_USER"
)

if [[ "$DB_HOST" == "localhost" && -S "$DB_SOCKET" ]]; then
  MYSQL_ARGS+=(--socket="$DB_SOCKET")
fi

MYSQLDUMP_ARGS=(
  --single-transaction
  --triggers
  --default-character-set=utf8mb4
)

if [[ "$INCLUDE_ROUTINES" == "1" || "$INCLUDE_ROUTINES" == "true" || "$INCLUDE_ROUTINES" == "yes" ]]; then
  MYSQLDUMP_ARGS+=(--routines)
fi

MYSQL_PWD="$DB_PASSWORD" mysqldump \
  "${MYSQL_ARGS[@]}" \
  "${MYSQLDUMP_ARGS[@]}" \
  "$DB_NAME" | gzip > "$OUTPUT"

echo "$OUTPUT"
