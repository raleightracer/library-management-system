<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $input = Request::input();
    $action = Request::action($input);
    Request::requireCsrfForActions($action, $input, ['pay', 'waive', 'adjust']);
    $service = new FineService();

    switch ($action) {
        case 'list':
            $current = Auth::requireLogin();
            Response::success('Fines loaded.', ['fines' => $service->list($current)]);

        case 'pay':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['fine_id']);
            $fine = $service->pay((int)$input['fine_id'], (int)$admin['id']);
            Response::success('Fine marked as paid.', ['fine' => $fine]);

        case 'waive':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['fine_id']);
            $fine = $service->waive((int)$input['fine_id'], (int)$admin['id'], $input['reason'] ?? null);
            Response::success('Fine waived.', ['fine' => $fine]);

        case 'adjust':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['fine_id', 'amount']);
            $fine = $service->adjust((int)$input['fine_id'], (float)$input['amount'], (int)$admin['id']);
            Response::success('Fine adjusted.', ['fine' => $fine]);

        default:
            Response::error('Invalid fines action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
