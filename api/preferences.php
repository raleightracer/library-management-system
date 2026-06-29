<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $current = Auth::requireLogin();
    $input = Request::input();
    $action = Request::action($input) ?: 'get';
    Request::requireCsrfForActions($action, $input, ['update']);
    $service = new UserPreferenceService();

    switch ($action) {
        case 'get':
            Response::success('Preferences loaded.', [
                'preferences' => $service->getForUser((int)$current['id']),
            ]);

        case 'update':
            $preferences = $input['preferences'] ?? $input;
            if (!is_array($preferences)) {
                Response::error('Invalid preferences payload.', 422);
            }
            Response::success('Preferences updated.', [
                'preferences' => $service->updateForUser((int)$current['id'], $preferences),
            ]);

        default:
            Response::error('Invalid preferences action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
