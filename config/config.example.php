<?php
declare(strict_types=1);

return [
    'app' => [
        'env' => 'local',
        'debug' => true,
        'cookie_secure' => 'auto',
        'timezone' => 'Asia/Manila',
        'loan_period_days' => 14,
        'max_active_loans' => 5,
        'remember_days' => 30,
        'email_enabled' => false,
        'email_from' => 'no-reply@quadbyte-lms.local',
    ],
    'mail' => [
        'enabled' => false,
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
        'from' => 'no-reply@quadbyte-lms.local',
        'from_name' => 'SchoDex Library',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'quadbyte_lms',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'socket' => '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',
    ],
    'paymongo' => [
        'public_key' => 'pk_test_or_live_xxx',
        'secret_key' => 'sk_test_or_live_xxx',
        'webhook_secret' => 'whsk_xxx',
    ],
];
