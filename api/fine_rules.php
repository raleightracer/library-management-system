<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $input = Request::input();
    $action = Request::action($input) ?: 'list';
    Request::requireCsrfForActions($action, $input, ['create', 'update', 'delete', 'disable']);
    $service = new FineService();

    switch ($action) {
        case 'list':
            Auth::requireLogin();
            Response::success('Fine rules loaded.', ['fine_rules' => $service->listFineRules()]);

        case 'create':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['name']);
            Response::success('Fine rule created.', [
                'fine_rule' => $service->createFineRule($input, (int)$admin['id']),
            ], 201);

        case 'update':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['id', 'name']);
            Response::success('Fine rule updated.', [
                'fine_rule' => $service->updateFineRule((int)$input['id'], $input, (int)$admin['id']),
            ]);

        case 'delete':
        case 'disable':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['id']);
            Response::success('Fine rule disabled.', [
                'fine_rule' => $service->disableFineRule((int)$input['id'], (int)$admin['id']),
            ]);

        default:
            Response::error('Invalid fine rules action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
