# Local Configuration

SchoDex reads configuration in this order:

1. Environment variables already provided by Apache/PHP.
2. Project root `.env`, when present.
3. `config/local.php`, when present.
4. Local XAMPP defaults.

For a standard local XAMPP MySQL install, the defaults are:

```text
SCHODEX_DB_HOST=localhost
SCHODEX_DB_PORT=3306
SCHODEX_DB_NAME=quadbyte_lms
SCHODEX_DB_USER=root
SCHODEX_DB_PASSWORD=
SCHODEX_DB_CHARSET=utf8mb4
SCHODEX_DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
```

If your local database uses different credentials, use one of these options.

## Option A: `.env`

Copy `.env.example` to `.env` and adjust values:

```bash
cp .env.example .env
```

The `.env` file is ignored by Git.

## Option B: `config/local.php`

Copy `config/config.example.php` to `config/local.php` and adjust values:

```bash
cp config/config.example.php config/local.php
```

The `config/local.php` file is ignored by Git.

Do not put PayMongo secret keys or database passwords in frontend JavaScript.
