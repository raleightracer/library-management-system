<?php
declare(strict_types=1);

final class Response
{
    public static function json(
        bool $success,
        string $message = '',
        mixed $data = null,
        array $errors = [],
        int $status = 200,
        ?string $redirect = null
    ): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'redirect' => $redirect,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message = '', mixed $data = null, int $status = 200): void
    {
        self::json(true, $message, $data, [], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = []): void
    {
        self::json(false, $message, null, $errors, $status);
    }

    public static function exception(Throwable $e): void
    {
        error_log(sprintf(
            '[SchoDex] %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        $app = Database::config()['app'] ?? [];
        $isProduction = ($app['env'] ?? 'local') === 'production';
        $debug = (bool)($app['debug'] ?? !$isProduction);
        $message = $isProduction && !$debug
            ? 'An internal server error occurred.'
            : $e->getMessage();

        self::error($message, 500);
    }
}
