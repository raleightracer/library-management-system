<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $input = Request::input();
    $action = Request::action($input);
    Request::requireCsrfForActions($action, $input, ['create', 'update', 'delete']);
    $service = new BookService();

    switch ($action) {
        case 'list':
            Auth::requireLogin();
            Response::success('Books loaded.', ['books' => $service->list($input)]);

        case 'get':
            Auth::requireLogin();
            Request::requireFields($input, ['id']);
            $book = $service->find((int)$input['id']);
            if (!$book) {
                Response::error('Book not found.', 404);
            }
            Response::success('Book loaded.', ['book' => $book]);

        case 'create':
            $admin = Auth::requireAdmin();
            $book = $service->create($input, (int)$admin['id']);
            Response::success('Book created.', ['book' => $book], 201);

        case 'update':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['id']);
            $book = $service->update((int)$input['id'], $input, (int)$admin['id']);
            Response::success('Book updated.', ['book' => $book]);

        case 'delete':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['id']);
            $service->softDelete((int)$input['id'], (int)$admin['id']);
            Response::success('Book deleted.');

        default:
            Response::error('Invalid books action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
