<?php
declare(strict_types=1);

$envPaths = [
    dirname(__DIR__) . '/.env',
    dirname(__DIR__) . '/.env.quadbyte_lms',
];

foreach ($envPaths as $envPath) {
    if (!is_file($envPath) || !is_readable($envPath)) {
        continue;
    }

    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$localConfigPath = __DIR__ . '/local.php';
$local = is_file($localConfigPath) ? require $localConfigPath : [];
if (!is_array($local)) {
    $local = [];
}

$defaultMysqlSocket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
$defaultMysqlSocket = is_readable($defaultMysqlSocket) ? $defaultMysqlSocket : '';

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
};

$localValue = static function (array $path, mixed $default = null) use ($local): mixed {
    $value = $local;
    foreach ($path as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
};

$paymongoPublicKey = (string)$env('PAYMONGO_PUBLIC_KEY', $localValue(['paymongo', 'public_key'], ''));
$paymongoSecretKey = (string)$env('PAYMONGO_SECRET_KEY', $localValue(['paymongo', 'secret_key'], ''));
$paymongoWebhookSecret = (string)$env('PAYMONGO_WEBHOOK_SECRET', $localValue(['paymongo', 'webhook_secret'], ''));

if (!defined('PAYMONGO_PUBLIC_KEY')) {
    define('PAYMONGO_PUBLIC_KEY', $paymongoPublicKey);
}

if (!defined('PAYMONGO_SECRET_KEY')) {
    define('PAYMONGO_SECRET_KEY', $paymongoSecretKey);
}

if (!defined('PAYMONGO_WEBHOOK_SECRET')) {
    define('PAYMONGO_WEBHOOK_SECRET', $paymongoWebhookSecret);
}

$appEnv = strtolower((string)$env('SCHODEX_APP_ENV', $localValue(['app', 'env'], 'local')));
$appDebugDefault = $appEnv !== 'production';

return [
    'app' => [
        'env' => $appEnv,
        'debug' => filter_var($env('SCHODEX_APP_DEBUG', $localValue(['app', 'debug'], $appDebugDefault)), FILTER_VALIDATE_BOOL),
        'cookie_secure' => (string)$env('SCHODEX_COOKIE_SECURE', $localValue(['app', 'cookie_secure'], 'auto')),
        'timezone' => (string)$env('SCHODEX_TIMEZONE', $localValue(['app', 'timezone'], 'Asia/Manila')),
        'loan_period_days' => (int)$env('SCHODEX_LOAN_PERIOD_DAYS', $localValue(['app', 'loan_period_days'], 14)),
        'max_active_loans' => (int)$env('SCHODEX_MAX_ACTIVE_LOANS', $localValue(['app', 'max_active_loans'], 5)),
        'remember_days' => (int)$env('SCHODEX_REMEMBER_DAYS', $localValue(['app', 'remember_days'], 30)),
        'email_enabled' => filter_var($env('SCHODEX_EMAIL_ENABLED', $localValue(['app', 'email_enabled'], false)), FILTER_VALIDATE_BOOL),
        'email_from' => (string)$env('SCHODEX_EMAIL_FROM', $localValue(['app', 'email_from'], 'no-reply@quadbyte-lms.local')),
    ],
    'mail' => [
        'enabled' => filter_var($env('SCHODEX_MAIL_ENABLED', $localValue(['mail', 'enabled'], false)), FILTER_VALIDATE_BOOL),
        'host' => (string)$env('SCHODEX_SMTP_HOST', $localValue(['mail', 'host'], '')),
        'port' => (int)$env('SCHODEX_SMTP_PORT', $localValue(['mail', 'port'], 587)),
        'username' => (string)$env('SCHODEX_SMTP_USERNAME', $localValue(['mail', 'username'], '')),
        'password' => (string)$env('SCHODEX_SMTP_PASSWORD', $localValue(['mail', 'password'], '')),
        'encryption' => (string)$env('SCHODEX_SMTP_ENCRYPTION', $localValue(['mail', 'encryption'], 'tls')),
        'from' => (string)$env('SCHODEX_MAIL_FROM', $localValue(['mail', 'from'], 'no-reply@quadbyte-lms.local')),
        'from_name' => (string)$env('SCHODEX_MAIL_FROM_NAME', $localValue(['mail', 'from_name'], 'SchoDex Library')),
    ],
    'db' => [
        'host' => (string)$env('SCHODEX_DB_HOST', $localValue(['db', 'host'], 'localhost')),
        'port' => (string)$env('SCHODEX_DB_PORT', $localValue(['db', 'port'], '3306')),
        'name' => (string)$env('SCHODEX_DB_NAME', $localValue(['db', 'name'], 'quadbyte_lms')),
        'user' => (string)$env('SCHODEX_DB_USER', $localValue(['db', 'user'], 'root')),
        'password' => (string)$env('SCHODEX_DB_PASSWORD', $localValue(['db', 'password'], '')),
        'charset' => (string)$env('SCHODEX_DB_CHARSET', $localValue(['db', 'charset'], 'utf8mb4')),
        'socket' => (string)$env('SCHODEX_DB_SOCKET', $localValue(['db', 'socket'], $defaultMysqlSocket)),
    ],
    'paymongo' => [
        'public_key' => $paymongoPublicKey,
        'secret_key' => $paymongoSecretKey,
        'webhook_secret' => $paymongoWebhookSecret,
    ],
];
