<?php
declare(strict_types=1);

date_default_timezone_set((require __DIR__ . '/config.php')['app']['timezone'] ?? 'Asia/Manila');

spl_autoload_register(static function (string $class): void {
    if ($class === 'AppNormalizer') {
        require_once __DIR__ . '/../services/Normalizer.php';
        return;
    }

    $roots = [
        __DIR__ . '/../core',
        __DIR__ . '/../models',
        __DIR__ . '/../services',
    ];

    foreach ($roots as $root) {
        $path = $root . '/' . $class . '.php';
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

Auth::start();
Auth::loginFromRememberCookie();
