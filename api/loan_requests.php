<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $input = Request::input();
    $action = Request::action($input);
    Request::requireCsrfForActions($action, $input, ['create', 'approve', 'reject', 'cancel']);
    $service = new CirculationService();

    switch ($action) {
        case 'list':
            $current = Auth::requireLogin();
            Response::success('Borrow requests loaded.', ['requests' => $service->listRequests($current)]);

        case 'create':
            $current = Auth::requireMember();
            Request::requireFields($input, ['book_id']);
            $request = $service->requestLoan((int)$current['id'], (int)$input['book_id'], $input['due_date'] ?? $input['requested_due_date'] ?? null);
            Response::success('Borrow request submitted.', ['request' => $request], 201);

        case 'approve':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['request_id']);
            $request = $service->approveRequest((int)$input['request_id'], (int)$admin['id']);
            Response::success('Borrow request approved.', ['request' => $request]);

        case 'reject':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['request_id']);
            $request = $service->rejectRequest((int)$input['request_id'], (int)$admin['id'], $input['reason'] ?? null);
            Response::success('Borrow request rejected.', ['request' => $request]);

        case 'cancel':
            $current = Auth::requireMember();
            Request::requireFields($input, ['request_id']);
            $request = $service->cancelRequest((int)$input['request_id'], (int)$current['id']);
            Response::success('Borrow request cancelled.', ['request' => $request]);

        default:
            Response::error('Invalid loan request action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
