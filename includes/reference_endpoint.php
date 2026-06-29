<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

function handle_reference_endpoint(string $table): void
{
    try {
        $input = Request::input();
        $action = Request::action($input) ?: 'list';
        Request::requireCsrfForActions($action, $input, ['create', 'update', 'delete']);
        $service = new ReferenceService();

        switch ($action) {
            case 'list':
                Auth::requireLogin();
                Response::success('Reference data loaded.', ['items' => $service->list($table)]);

            case 'create':
                $admin = Auth::requireAdmin();
                Request::requireFields($input, ['name']);
                Response::success('Reference created.', ['item' => $service->create($table, (string)$input['name'], (int)$admin['id'])], 201);

            case 'update':
                $admin = Auth::requireAdmin();
                Request::requireFields($input, ['id', 'name']);
                Response::success('Reference updated.', ['item' => $service->update($table, (int)$input['id'], (string)$input['name'], (int)$admin['id'])]);

            case 'delete':
                $admin = Auth::requireAdmin();
                Request::requireFields($input, ['id']);
                $service->delete($table, (int)$input['id'], (int)$admin['id']);
                Response::success('Reference deleted.');

            default:
                Response::error('Invalid reference action.', 400);
        }
    } catch (Throwable $e) {
        Response::exception($e);
    }
}
