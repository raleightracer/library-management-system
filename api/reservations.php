<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $input = Request::input();
    $action = Request::action($input);
    Request::requireCsrfForActions($action, $input, ['create', 'ready', 'mark_ready', 'fulfill', 'complete', 'cancel', 'expire']);
    $service = new CirculationService();

    switch ($action) {
        case 'list':
            $current = Auth::requireLogin();
            Response::success('Reservations loaded.', ['reservations' => $service->listReservations($current)]);

        case 'create':
            $current = Auth::requireMember();
            Request::requireFields($input, ['book_id']);
            $reservation = $service->reserve((int)$current['id'], (int)$input['book_id']);
            Response::success('Reservation created.', ['reservation' => $reservation], 201);

        case 'cancel':
            $current = Auth::requireLogin();
            if (!in_array(($current['role_slug'] ?? null), ['admin', 'member'], true)) {
                Response::error('Member or admin access required.', 403);
            }
            if (!empty($input['reservation_id'])) {
                $service->cancelReservation((int)$input['reservation_id'], $current);
            } else {
                if (($current['role_slug'] ?? null) !== 'member') {
                    Response::error('Member access required.', 403);
                }
                Request::requireFields($input, ['book_id']);
                $service->cancelReservationByBook((int)$input['book_id'], $current);
            }
            Response::success('Reservation cancelled.');

        case 'ready':
        case 'mark_ready':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['reservation_id']);
            $reservation = $service->markReservationReady((int)$input['reservation_id'], (int)$admin['id']);
            Response::success('Reservation marked ready for pickup.', ['reservation' => $reservation]);

        case 'fulfill':
        case 'complete':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['reservation_id']);
            $data = $service->fulfillReservation((int)$input['reservation_id'], (int)$admin['id'], $input['due_date'] ?? null);
            Response::success('Reservation fulfilled.', $data);

        case 'expire':
            $admin = Auth::requireAdmin();
            Request::requireFields($input, ['reservation_id']);
            $reservation = $service->expireReservation((int)$input['reservation_id'], (int)$admin['id']);
            Response::success('Reservation expired.', ['reservation' => $reservation]);

        default:
            Response::error('Invalid reservation action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
