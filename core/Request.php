<?php
declare(strict_types=1);

final class Request
{
    public static function input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (str_contains(strtolower($contentType), 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                return $_GET;
            }

            return array_merge($_GET, $data);
        }

        if ($method === 'GET') {
            return $_GET;
        }

        return array_merge($_GET, $_POST);
    }

    public static function action(array $input): string
    {
        return trim((string)($input['action'] ?? ''));
    }

    public static function requireFields(array $input, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field) {
            if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
                $errors[$field] = 'This field is required.';
            }
        }

        if ($errors !== []) {
            Response::error('Please complete all required fields.', 422, $errors);
        }
    }

    public static function requireCsrfForActions(string $action, array $input, array $actions): void
    {
        if (in_array($action, $actions, true)) {
            Auth::requireCsrfToken($input);
        }
    }
}
