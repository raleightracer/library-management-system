<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    Auth::requireAdmin();
    $input = Request::input();
    $action = Request::action($input) ?: 'all';
    $service = new ReportService();
    $from = $input['from'] ?? null;
    $to = $input['to'] ?? null;

    $filters = [
        'from' => $from,
        'to' => $to,
        'user' => $input['user'] ?? '',
        'status' => $input['status'] ?? 'all',
    ];

    $data = match ($action) {
        'loan_report' => ['rows' => $service->loanDetailReport((string)($input['report'] ?? 'issued'), $filters)],
        'fine_report' => $service->fineDetailReport($filters),
        'issued_books' => ['issued_books' => $service->issuedBooks($from, $to)],
        'overdue_books' => ['overdue_books' => $service->overdueBooks()],
        'returned_books' => ['returned_books' => $service->returnedBooks($from, $to)],
        'fines_collected' => ['fines_collected' => $service->finesCollected($from, $to)],
        'active_members' => ['active_members' => $service->activeMembers()],
        'popular_books' => ['popular_books' => $service->popularBooks()],
        'all' => $service->all($from, $to),
        default => null,
    };

    if ($data === null) {
        Response::error('Invalid reports action.', 400);
    }

    Response::success('Report loaded.', $data);
} catch (Throwable $e) {
    Response::exception($e);
}
