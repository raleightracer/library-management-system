<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $input = Request::input();
    $action = Request::action($input);
    Request::requireCsrfForActions($action, $input, ['issue', 'return', 'mark_lost', 'update', 'renew', 'check_overdues']);
    $service = new CirculationService();

    switch ($action) {
        case 'list':
            $current = Auth::requireLogin();
            Response::success('Loans loaded.', ['transactions' => $service->listLoans($current)]);

        case 'issue':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['book_id', 'member_id']);
            $loan = $service->issueDirect((int)$input['book_id'], (int)$input['member_id'], (int)$admin['id'], $input['due_date'] ?? null);
            Response::success('Book issued.', ['loan' => $loan], 201);

        case 'return':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['transaction_id']);
            $data = $service->returnLoan((int)$input['transaction_id'], (int)$admin['id']);
            Response::success('Book returned.', $data);

        case 'mark_lost':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['transaction_id']);
            $data = $service->markLostOrDamaged((int)$input['transaction_id'], (float)($input['amount'] ?? 0), (int)$admin['id'], $input['type'] ?? 'lost');
            Response::success('Book marked as lost/damaged.', $data);

        case 'update':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['transaction_id']);
            $loan = $service->updateLoan((int)$input['transaction_id'], $input, (int)$admin['id']);
            Response::success('Loan updated.', ['loan' => $loan]);

        case 'renew':
            $current = Auth::requireLogin();
            Request::requireFields($input, ['transaction_id']);
            $loan = $service->renew((int)$input['transaction_id'], $current);
            Response::success('Loan renewed.', ['loan' => $loan]);

        case 'check_overdues':
            // Global overdue notification generation should be run by an admin or a future scheduled job.
            Auth::requireAdmin();
            $service->generateOverdueNotifications();
            Response::success('Overdue notifications checked.');

        default:
            Response::error('Invalid loans action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
