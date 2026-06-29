<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $input = Request::input();
    $action = Request::action($input);
    Request::requireCsrfForActions($action, $input, [
        'mark_all_read',
        'mark_single_read',
        'mark_single_unread',
        'delete',
        'clear_all',
    ]);
    $current = Auth::requireLogin();
    $service = new NotificationService();

    switch ($action) {
        case 'list':
            Response::success('Notifications loaded.', ['notifications' => $service->listForCurrentUser($current)]);

        case 'mark_all_read':
            $service->markAllRead($current);
            Response::success('Notifications marked as read.');

        case 'mark_single_read':
            Request::requireFields($input, ['id']);
            $service->markSingleRead((int)$input['id'], $current);
            Response::success('Notification marked as read.');

        case 'mark_single_unread':
            Request::requireFields($input, ['id']);
            $service->markSingleUnread((int)$input['id'], $current);
            Response::success('Notification marked as unread.');

        case 'delete':
            Request::requireFields($input, ['id']);
            $service->deleteSingle((int)$input['id'], $current);
            Response::success('Notification deleted.');

        case 'clear_all':
            $deleted = $service->clearForCurrentUser($current);
            Response::success('Notifications cleared.', ['deleted' => $deleted]);

        default:
            Response::error('Invalid notifications action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
