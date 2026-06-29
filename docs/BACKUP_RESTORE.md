# SchoDex Backup and Restore Runbook

This project stores application data in MySQL. Backups should be taken before schema changes, backend feature work, deployments, and any data cleanup.

## Backup Location

Use a local, non-versioned directory:

```bash
mkdir -p backups
```

The `backups/` directory and generated `.sql` / `.sql.gz` files are ignored by Git.

## Configuration

The helper scripts read database values from environment variables. If a local `.env` file exists in the project root, the scripts load it automatically.

Required values:

```bash
SCHODEX_DB_HOST=127.0.0.1
SCHODEX_DB_PORT=3306
SCHODEX_DB_NAME=quadbyte_lms
SCHODEX_DB_USER=root
SCHODEX_DB_PASSWORD=
SCHODEX_DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
```

## Naming Convention

Backups use this format:

```text
schodex_lms_YYYYmmdd_HHMMSS.sql.gz
```

Example:

```text
schodex_lms_20260503_213045.sql.gz
```

## Create a Backup

Preferred helper command:

```bash
bash scripts/backup_database.sh
```

Optional custom output directory:

```bash
BACKUP_DIR=/private/tmp/schodex_backups bash scripts/backup_database.sh
```

Routines are not included by default because SchoDex does not define stored procedures/functions. If your deployment adds routines, enable them explicitly:

```bash
SCHODEX_BACKUP_ROUTINES=true bash scripts/backup_database.sh
```

Equivalent manual command:

```bash
mysqldump \
  --host="$SCHODEX_DB_HOST" \
  --port="$SCHODEX_DB_PORT" \
  --user="$SCHODEX_DB_USER" \
  --password="$SCHODEX_DB_PASSWORD" \
  --single-transaction \
  --triggers \
  --default-character-set=utf8mb4 \
  "$SCHODEX_DB_NAME" | gzip > "backups/schodex_lms_$(date +%Y%m%d_%H%M%S).sql.gz"
```

## Restore a Backup

Restore into an existing database:

```bash
bash scripts/restore_database.sh backups/schodex_lms_20260503_213045.sql.gz
```

Equivalent manual command:

```bash
gunzip -c backups/schodex_lms_20260503_213045.sql.gz | mysql \
  --host="$SCHODEX_DB_HOST" \
  --port="$SCHODEX_DB_PORT" \
  --user="$SCHODEX_DB_USER" \
  --password="$SCHODEX_DB_PASSWORD" \
  --default-character-set=utf8mb4 \
  "$SCHODEX_DB_NAME"
```

For plain `.sql` files:

```bash
mysql \
  --host="$SCHODEX_DB_HOST" \
  --port="$SCHODEX_DB_PORT" \
  --user="$SCHODEX_DB_USER" \
  --password="$SCHODEX_DB_PASSWORD" \
  --default-character-set=utf8mb4 \
  "$SCHODEX_DB_NAME" < backups/schodex_lms_20260503_213045.sql
```

## Restore Safety Checklist

1. Confirm the target database name with `echo "$SCHODEX_DB_NAME"`.
2. Take a fresh backup before restoring over existing data.
3. Restore to a staging database first when possible.
4. Confirm the application can log in and load catalog, loans, members, reservations, and fines after restore.

## Retention Notes

Suggested local retention:

- Keep daily backups for 14 days.
- Keep weekly backups for 8 weeks.
- Keep monthly backups for 12 months.
- Store production backups outside the web root and copy them to secured offsite storage.

Generated backups may contain personal data, loan history, fines, and payment references. Treat them as sensitive files.
