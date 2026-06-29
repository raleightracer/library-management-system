<?php
declare(strict_types=1);

final class CirculationService extends BaseModel
{
    private const RESERVATION_WAITING_STATUSES = ['pending', 'active'];
    private const RESERVATION_OPEN_STATUSES = ['pending', 'active', 'ready_for_pickup'];

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        new UserPreferenceService($this->db);
        new NotificationService($this->db);
    }

    public function listLoans(array $currentUser): array
    {
        if (($currentUser['role_slug'] ?? '') === 'admin') {
            $stmt = $this->db->query($this->loanSql() . ' ORDER BY lt.borrowed_at DESC, lt.id DESC');
        } else {
            $stmt = $this->db->prepare($this->loanSql() . ' WHERE lt.member_id = :member_id ORDER BY lt.borrowed_at DESC, lt.id DESC');
            $stmt->execute(['member_id' => (int)$currentUser['member_id']]);
        }

        return array_map([AppNormalizer::class, 'loan'], $stmt->fetchAll());
    }

    public function listRequests(array $currentUser): array
    {
        if (($currentUser['role_slug'] ?? '') === 'admin') {
            $stmt = $this->db->query($this->requestSql() . ' ORDER BY lr.created_at DESC, lr.id DESC');
        } else {
            $stmt = $this->db->prepare($this->requestSql() . ' WHERE lr.member_id = :member_id ORDER BY lr.created_at DESC, lr.id DESC');
            $stmt->execute(['member_id' => (int)$currentUser['member_id']]);
        }

        return array_map([AppNormalizer::class, 'request'], $stmt->fetchAll());
    }

    public function listReservations(array $currentUser): array
    {
        if (($currentUser['role_slug'] ?? '') === 'admin') {
            $stmt = $this->db->query($this->reservationSql() . ' ORDER BY r.created_at DESC, r.id DESC');
        } else {
            $stmt = $this->db->prepare($this->reservationSql() . ' WHERE r.member_id = :member_id ORDER BY r.created_at DESC, r.id DESC');
            $stmt->execute(['member_id' => (int)$currentUser['member_id']]);
        }

        return array_map([AppNormalizer::class, 'reservation'], $stmt->fetchAll());
    }

    public function requestLoan(int $userId, int $bookId, ?string $requestedDueDate = null): array
    {
        $member = $this->memberForUser($userId);
        $this->assertCanBorrow((int)$member['id']);

        $book = $this->bookRow($bookId);
        if (!$book) {
            Response::error('Book not found.', 404);
        }

        $this->assertNoActiveLoanForBook((int)$member['id'], $bookId);
        $dueDate = $this->normalizedDueDate($requestedDueDate);
        if ($this->pendingRequestCount((int)$member['id']) >= 5) {
            Response::error('Maximum pending borrow requests reached', 409);
        }

        $stmt = $this->db->prepare(
            "SELECT id FROM loan_requests
             WHERE member_id = :member_id AND book_id = :book_id AND status = 'pending'
             LIMIT 1"
        );
        $stmt->execute(['member_id' => (int)$member['id'], 'book_id' => $bookId]);
        if ($stmt->fetch()) {
            Response::error('You already have a pending request for this book.', 409);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO loan_requests (member_id, book_id, requested_due_date, status, created_at, updated_at)
             VALUES (:member_id, :book_id, :requested_due_date, "pending", NOW(), NOW())'
        );
        $stmt->execute([
            'member_id' => (int)$member['id'],
            'book_id' => $bookId,
            'requested_due_date' => $dueDate,
        ]);
        $requestId = (int)$this->db->lastInsertId();

        $memberName = trim((string)$member['first_name'] . ' ' . (string)$member['last_name']);
        $notes = new NotificationService($this->db);
        $notes->create($userId, null, 'Borrow request submitted', 'Your borrow request for "' . $book['title'] . '" has been submitted.', 'info', null, 'borrow_request', $requestId, 'submitted');
        $notes->create(null, 'admin', 'New borrow request', 'New borrow request: "' . $book['title'] . '" by ' . $memberName . '.', 'info', null, 'borrow_request', $requestId, 'submitted');

        return $this->findRequest($requestId);
    }

    public function approveRequest(int $requestId, int $adminId): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT lr.*, m.user_id, b.title
                 FROM loan_requests lr
                 INNER JOIN members m ON m.id = lr.member_id
                 INNER JOIN books b ON b.id = lr.book_id
                 WHERE lr.id = :id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $requestId]);
            $request = $stmt->fetch();
            if (!$request) {
                Response::error('Borrow request not found.', 404);
            }
            if ($request['status'] !== 'pending') {
                Response::error('This request has already been processed.', 409);
            }

            $loan = $this->issueLocked((int)$request['member_id'], (int)$request['book_id'], $adminId, (string)$request['requested_due_date'], $requestId);

            $update = $this->db->prepare(
                "UPDATE loan_requests
                 SET status = 'approved', reviewed_by = :reviewed_by, reviewed_at = NOW(), updated_at = NOW()
                 WHERE id = :id AND status = 'pending'"
            );
            $update->execute(['reviewed_by' => $adminId, 'id' => $requestId]);
            if ($update->rowCount() !== 1) {
                Response::error('This request has already been processed.', 409);
            }

            (new NotificationService($this->db))->create((int)$request['user_id'], null, 'Borrow request approved', 'Your borrow request for "' . $request['title'] . '" has been approved.', 'info', (int)$loan['id'], 'loan', (int)$loan['id'], 'approved');
            (new AuditService($this->db))->log($adminId, 'approve', 'loan_requests', $requestId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findRequest($requestId);
    }

    public function rejectRequest(int $requestId, int $adminId, ?string $reason = null): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT lr.*, m.user_id, b.title
                 FROM loan_requests lr
                 INNER JOIN members m ON m.id = lr.member_id
                 INNER JOIN books b ON b.id = lr.book_id
                 WHERE lr.id = :id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $requestId]);
            $request = $stmt->fetch();
            if (!$request) {
                Response::error('Borrow request not found.', 404);
            }
            if ($request['status'] !== 'pending') {
                Response::error('This request has already been processed.', 409);
            }

            $update = $this->db->prepare(
                "UPDATE loan_requests
                 SET status = 'rejected', rejection_reason = :reason, reviewed_by = :reviewed_by, reviewed_at = NOW(), updated_at = NOW()
                 WHERE id = :id AND status = 'pending'"
            );
            $update->execute(['reason' => $reason, 'reviewed_by' => $adminId, 'id' => $requestId]);
            if ($update->rowCount() !== 1) {
                Response::error('This request has already been processed.', 409);
            }

            (new NotificationService($this->db))->create((int)$request['user_id'], null, 'Borrow request rejected', 'Your borrow request for "' . $request['title'] . '" was rejected.', 'info', null, 'borrow_request', $requestId, 'rejected');
            (new AuditService($this->db))->log($adminId, 'reject', 'loan_requests', $requestId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findRequest($requestId);
    }

    public function cancelRequest(int $requestId, int $userId): array
    {
        $member = $this->memberForUser($userId);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT lr.*, b.title
                 FROM loan_requests lr
                 INNER JOIN books b ON b.id = lr.book_id
                 WHERE lr.id = :id AND lr.member_id = :member_id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $requestId, 'member_id' => (int)$member['id']]);
            $request = $stmt->fetch();
            if (!$request) {
                Response::error('Borrow request not found.', 404);
            }
            if ($request['status'] !== 'pending') {
                Response::error('This request has already been processed.', 409);
            }

            $update = $this->db->prepare(
                "UPDATE loan_requests
                 SET status = 'cancelled', reviewed_at = NOW(), updated_at = NOW()
                 WHERE id = :id AND status = 'pending'"
            );
            $update->execute(['id' => $requestId]);
            if ($update->rowCount() !== 1) {
                Response::error('This request has already been processed.', 409);
            }

            (new NotificationService($this->db))->create($userId, null, 'Borrow request cancelled', 'Your borrow request for "' . $request['title'] . '" was cancelled.', 'info', null, 'borrow_request', $requestId, 'cancelled');
            (new AuditService($this->db))->log($userId, 'cancel', 'loan_requests', $requestId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findRequest($requestId);
    }

    public function issueDirect(int $bookId, int $memberOrUserId, int $adminId, ?string $dueDate = null): array
    {
        $member = $this->memberForMemberOrUser($memberOrUserId);

        $this->db->beginTransaction();
        try {
            $loan = $this->issueLocked((int)$member['id'], $bookId, $adminId, $this->normalizedDueDate($dueDate), null);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $loan;
    }

    public function returnLoan(int $loanId, int $adminId): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT lt.*, b.title, b.late_fee_per_day, b.replacement_value, m.user_id
                 FROM loan_transactions lt
                 INNER JOIN books b ON b.id = lt.book_id
                 INNER JOIN members m ON m.id = lt.member_id
                 WHERE lt.id = :id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $loanId]);
            $loan = $stmt->fetch();
            if (!$loan) {
                Response::error('Loan not found.', 404);
            }
            if ($loan['status'] !== 'borrowed') {
                Response::error('This loan is already closed.', 409);
            }

            $fine = new FineService($this->db);
            $rule = $fine->overdueRule();
            $daysLate = $this->daysLate((string)$loan['due_date'], (int)($rule['grace_days'] ?? 1));
            $rate = (float)($loan['late_fee_per_day'] ?: ($rule['amount_per_day'] ?? 20));
            $amount = $daysLate * $rate;

            $loanUpdate = $this->db->prepare(
                "UPDATE loan_transactions
                 SET status = 'returned', returned_at = NOW(), return_condition = 'good', updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id AND status = 'borrowed'"
            );
            $loanUpdate->execute(['updated_by' => $adminId, 'id' => $loanId]);
            if ($loanUpdate->rowCount() !== 1) {
                Response::error('This loan is already closed.', 409);
            }

            $copyUpdate = $this->db->prepare(
                "UPDATE book_copies SET status = 'available', updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND status = 'borrowed'"
            );
            $copyUpdate->execute(['updated_by' => $adminId, 'id' => (int)$loan['copy_id']]);
            if ($copyUpdate->rowCount() !== 1) {
                Response::error('Borrowed copy state could not be restored.', 409);
            }

            if ($amount > 0) {
                $reason = sprintf('Late return - %d day(s) overdue (₱%.2f/day)', $daysLate, $rate);
                $fineId = $fine->createFine((int)$loan['member_id'], $loanId, (int)$loan['book_id'], 'overdue', $amount, $reason, $adminId);
                (new NotificationService($this->db))->create((int)$loan['user_id'], null, 'Late fine assessed', 'A fine of ₱' . number_format($amount, 2) . ' was added for "' . $loan['title'] . '".', 'overdue', $loanId, 'fine', $fineId, 'assessed');
            }

            $this->promoteNextReservation((int)$loan['book_id']);
            (new AuditService($this->db))->log($adminId, 'return', 'loan_transactions', $loanId, ['fine_amount' => $amount]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return [
            'loan' => $this->findLoan($loanId),
            'days_late' => $daysLate ?? 0,
            'fine_amount' => $amount ?? 0,
            'fine_id' => $fineId ?? null,
        ];
    }

    public function markLostOrDamaged(int $loanId, float $amount, int $adminId, string $type = 'lost'): array
    {
        $type = $type === 'damaged' ? 'damaged' : 'lost';
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT lt.*, b.title, b.replacement_value, b.price, m.user_id
                 FROM loan_transactions lt
                 INNER JOIN books b ON b.id = lt.book_id
                 INNER JOIN members m ON m.id = lt.member_id
                 WHERE lt.id = :id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $loanId]);
            $loan = $stmt->fetch();
            if (!$loan || $loan['status'] !== 'borrowed') {
                Response::error('Active loan not found.', 404);
            }

            $fine = new FineService($this->db);
            $replacement = $fine->replacementAmount($loan, $amount);

            $loanUpdate = $this->db->prepare(
                "UPDATE loan_transactions
                 SET status = :status, returned_at = NOW(), return_condition = :return_condition, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id AND status = 'borrowed'"
            );
            $loanUpdate->execute(['status' => $type, 'return_condition' => $type, 'updated_by' => $adminId, 'id' => $loanId]);
            if ($loanUpdate->rowCount() !== 1) {
                Response::error('Active loan not found.', 409);
            }

            $copyUpdate = $this->db->prepare(
                "UPDATE book_copies SET status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND status = 'borrowed'"
            );
            $copyUpdate->execute(['status' => $type, 'updated_by' => $adminId, 'id' => (int)$loan['copy_id']]);
            if ($copyUpdate->rowCount() !== 1) {
                Response::error('Borrowed copy state could not be closed.', 409);
            }

            $reason = ucfirst($type) . ' book - "' . $loan['title'] . '" replacement fee';
            $fineId = $fine->createFine((int)$loan['member_id'], $loanId, (int)$loan['book_id'], $type, $replacement, $reason, $adminId);

            $notes = new NotificationService($this->db);
            $notes->create((int)$loan['user_id'], null, ucfirst($type) . ' item fine', 'A replacement fine of ₱' . number_format($replacement, 2) . ' was charged for "' . $loan['title'] . '".', 'overdue', $loanId, 'fine', $fineId, 'assessed');
            $notes->create(null, 'admin', ucfirst($type) . ' item recorded', '"' . $loan['title'] . '" was marked ' . $type . '.', 'overdue', $loanId, 'loan', $loanId, $type);

            (new AuditService($this->db))->log($adminId, $type, 'loan_transactions', $loanId, ['fine_id' => $fineId]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['loan' => $this->findLoan($loanId), 'fine_id' => $fineId ?? null];
    }

    public function updateLoan(int $loanId, array $payload, int $adminId): array
    {
        $checkoutDate = $this->dateInput($payload['checkout_date'] ?? $payload['borrowed_at'] ?? null, 'Checkout date');
        $dueDate = $this->dateInput($payload['due_date'] ?? null, 'Due date');
        $returnDateInput = array_key_exists('return_date', $payload)
            ? $payload['return_date']
            : ($payload['returned_at'] ?? null);
        $returnDate = $returnDateInput === null || $returnDateInput === ''
            ? null
            : $this->dateInput($returnDateInput, 'Return date');

        if ($dueDate < $checkoutDate) {
            Response::error('Due date cannot be before checkout date.', 422);
        }
        if ($returnDate !== null && $returnDate < $checkoutDate) {
            Response::error('Return date cannot be before checkout date.', 422);
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM loan_transactions WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $loanId]);
            $loan = $stmt->fetch();
            if (!$loan) {
                Response::error('Loan not found.', 404);
            }

            $currentStatus = (string)$loan['status'];
            $requestedStatus = isset($payload['status']) && $payload['status'] !== ''
                ? (string)$payload['status']
                : $currentStatus;
            if (!in_array($requestedStatus, ['borrowed', 'returned', 'lost', 'damaged'], true)) {
                Response::error('Invalid loan status.', 422);
            }
            if ($requestedStatus !== $currentStatus) {
                Response::error('Loan status changes must use Return, Damage, or Loss actions.', 409);
            }
            if ($currentStatus === 'borrowed' && $returnDate !== null) {
                Response::error('Active borrowed loans cannot have a return date. Use Return, Damage, or Loss.', 409);
            }
            if (in_array($currentStatus, ['returned', 'lost', 'damaged'], true) && $returnDate === null) {
                Response::error('Closed loans require a return date.', 422);
            }

            $this->db->prepare(
                'UPDATE loan_transactions
                 SET borrowed_at = :borrowed_at,
                     due_date = :due_date,
                     returned_at = :returned_at,
                     updated_by = :updated_by,
                     updated_at = NOW()
                 WHERE id = :id'
            )->execute([
                'borrowed_at' => $checkoutDate . ' 00:00:00',
                'due_date' => $dueDate,
                'returned_at' => $returnDate ? $returnDate . ' 00:00:00' : null,
                'updated_by' => $adminId,
                'id' => $loanId,
            ]);

            (new AuditService($this->db))->log($adminId, 'update', 'loan_transactions', $loanId, [
                'checkout_date' => $checkoutDate,
                'due_date' => $dueDate,
                'return_date' => $returnDate,
                'status' => $currentStatus,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findLoan($loanId);
    }

    public function renew(int $loanId, array $currentUser): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT lt.*, b.title
                 FROM loan_transactions lt
                 INNER JOIN books b ON b.id = lt.book_id
                 WHERE lt.id = :id
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $loanId]);
            $loan = $stmt->fetch();
            if (!$loan || $loan['status'] !== 'borrowed') {
                Response::error('Active loan not found.', 404);
            }

            if (!in_array(($currentUser['role_slug'] ?? ''), ['admin', 'member'], true)) {
                Response::error('Member or admin access required.', 403);
            }
            if (($currentUser['role_slug'] ?? '') !== 'admin' && (int)$loan['member_id'] !== (int)$currentUser['member_id']) {
                Response::error('You can only renew your own loans.', 403);
            }
            if ((int)$loan['renew_count'] >= 1) {
                Response::error('Maximum renewals reached.', 409);
            }
            if ($this->daysLate((string)$loan['due_date'], 0) > 0) {
                Response::error('Overdue loans cannot be renewed.', 409);
            }

            $reservations = $this->db->prepare(
                "SELECT id FROM reservations
                 WHERE book_id = :book_id AND member_id <> :member_id
                   AND status IN ('pending','active','ready_for_pickup')
                 LIMIT 1"
            );
            $reservations->execute(['book_id' => (int)$loan['book_id'], 'member_id' => (int)$loan['member_id']]);
            if ($reservations->fetch()) {
                Response::error('Cannot renew because another member has reserved this book.', 409);
            }

            $days = (int)Database::config()['app']['loan_period_days'];
            $newDueDate = (new DateTimeImmutable((string)$loan['due_date']))->modify("+{$days} days")->format('Y-m-d');
            $renew = $this->db->prepare(
                "UPDATE loan_transactions
                 SET due_date = :due_date, renew_count = renew_count + 1, updated_at = NOW()
                 WHERE id = :id AND status = 'borrowed' AND renew_count < 1"
            );
            $renew->execute(['due_date' => $newDueDate, 'id' => $loanId]);
            if ($renew->rowCount() !== 1) {
                Response::error('Maximum renewals reached.', 409);
            }

            (new NotificationService($this->db))->create((int)$currentUser['id'], null, 'Loan renewed', '"' . $loan['title'] . '" was renewed.', 'info', $loanId, 'loan', $loanId, 'renewed');
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findLoan($loanId);
    }

    public function reserve(int $userId, int $bookId): array
    {
        $member = $this->memberForUser($userId);
        $this->db->beginTransaction();
        try {
            $book = $this->lockBookForUpdate($bookId);
            if ($this->availableCopies($bookId) > 0) {
                Response::error('Reservations are only allowed when no copies are available.', 409);
            }
            if ($this->hasActiveLoanForBook((int)$member['id'], $bookId)) {
                Response::error('You cannot reserve a book copy you already borrowed.', 409);
            }

            $existing = $this->db->prepare(
                "SELECT id FROM reservations
                 WHERE member_id = :member_id AND book_id = :book_id
                   AND status IN ('pending','active','ready_for_pickup')
                 LIMIT 1
                 FOR UPDATE"
            );
            $existing->execute(['member_id' => (int)$member['id'], 'book_id' => $bookId]);
            if ($existing->fetch()) {
                Response::error('You already reserved this book.', 409);
            }

            $position = $this->nextQueuePosition($bookId);
            $stmt = $this->db->prepare(
                'INSERT INTO reservations (member_id, book_id, status, queue_position, created_at, updated_at)
                 VALUES (:member_id, :book_id, "active", :queue_position, NOW(), NOW())'
            );
            $stmt->execute(['member_id' => (int)$member['id'], 'book_id' => $bookId, 'queue_position' => $position]);
            if ($stmt->rowCount() !== 1) {
                Response::error('Reservation could not be created.', 409);
            }
            $id = (int)$this->db->lastInsertId();

            (new NotificationService($this->db))->create($userId, null, 'Book reserved', 'You reserved "' . ($book['title'] ?? 'this book') . '".', 'info', null, 'reservation', $id, 'created');
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findReservation($id);
    }

    public function cancelReservation(int $reservationId, array $currentUser): void
    {
        $this->db->beginTransaction();
        try {
            $reservation = $this->reservationRowForUpdate($reservationId);
            if (($currentUser['role_slug'] ?? '') !== 'admin' && (int)$reservation['member_id'] !== (int)$currentUser['member_id']) {
                Response::error('You can only cancel your own reservations.', 403);
            }
            if (!in_array((string)$reservation['status'], self::RESERVATION_OPEN_STATUSES, true)) {
                Response::error('Only open reservations can be cancelled.', 409);
            }

            $this->lockBookForUpdate((int)$reservation['book_id']);
            $this->setReservationStatus($reservationId, 'cancelled');
            $this->renumberReservationQueue((int)$reservation['book_id']);
            $this->promoteNextReservation((int)$reservation['book_id']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function cancelReservationByBook(int $bookId, array $currentUser): void
    {
        if (($currentUser['role_slug'] ?? '') === 'admin') {
            Response::error('Member context required to cancel by book.', 422);
        }

        $stmt = $this->db->prepare(
            "SELECT id FROM reservations
             WHERE book_id = :book_id AND member_id = :member_id
               AND status IN ('pending','active','ready_for_pickup')
             ORDER BY queue_position, created_at
             LIMIT 1"
        );
        $stmt->execute(['book_id' => $bookId, 'member_id' => (int)$currentUser['member_id']]);
        $reservationId = (int)($stmt->fetchColumn() ?: 0);
        if ($reservationId <= 0) {
            Response::error('Open reservation not found.', 404);
        }

        $this->cancelReservation($reservationId, $currentUser);
    }

    public function markReservationReady(int $reservationId, int $adminId): array
    {
        $this->db->beginTransaction();
        try {
            $reservation = $this->reservationRowForUpdate($reservationId);
            $this->lockBookForUpdate((int)$reservation['book_id']);
            if (!in_array((string)$reservation['status'], self::RESERVATION_WAITING_STATUSES, true)) {
                Response::error('Only waiting reservations can be marked ready.', 409);
            }
            if ($this->availableCopies((int)$reservation['book_id']) <= 0) {
                Response::error('No available copies for this reservation.', 409);
            }
            if ($this->readyReservationExists((int)$reservation['book_id'])) {
                Response::error('Another reservation is already ready for pickup for this book.', 409);
            }

            $this->setReservationStatus($reservationId, 'ready_for_pickup', [
                'expires_at' => (new DateTimeImmutable('+3 days'))->format('Y-m-d 17:00:00'),
            ]);
            (new AuditService($this->db))->log($adminId, 'ready', 'reservations', $reservationId);
            $this->notifyReservationReadyById($reservationId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findReservation($reservationId);
    }

    public function fulfillReservation(int $reservationId, int $adminId, ?string $dueDate = null): array
    {
        $this->db->beginTransaction();
        try {
            $reservation = $this->reservationRowForUpdate($reservationId);
            $this->lockBookForUpdate((int)$reservation['book_id']);
            if (!in_array((string)$reservation['status'], ['ready_for_pickup', 'active', 'pending'], true)) {
                Response::error('Only open reservations can be fulfilled.', 409);
            }

            $loan = $this->issueLocked((int)$reservation['member_id'], (int)$reservation['book_id'], $adminId, $this->normalizedDueDate($dueDate), null);
            $this->setReservationStatus($reservationId, 'completed');
            (new AuditService($this->db))->log($adminId, 'fulfill', 'reservations', $reservationId, ['loan_id' => $loan['id'] ?? null]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return [
            'reservation' => $this->findReservation($reservationId),
            'loan' => $loan,
        ];
    }

    public function expireReservation(int $reservationId, int $adminId): array
    {
        $this->db->beginTransaction();
        try {
            $reservation = $this->reservationRowForUpdate($reservationId);
            $this->lockBookForUpdate((int)$reservation['book_id']);
            if (!in_array((string)$reservation['status'], self::RESERVATION_OPEN_STATUSES, true)) {
                Response::error('Only open reservations can be expired.', 409);
            }

            $this->setReservationStatus($reservationId, 'expired');
            $this->renumberReservationQueue((int)$reservation['book_id']);
            $this->promoteNextReservation((int)$reservation['book_id']);
            (new AuditService($this->db))->log($adminId, 'expire', 'reservations', $reservationId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findReservation($reservationId);
    }

    public function generateOverdueNotifications(): void
    {
        $stmt = $this->db->query(
            "SELECT lt.*, b.title, m.user_id
             FROM loan_transactions lt
             INNER JOIN books b ON b.id = lt.book_id
             INNER JOIN members m ON m.id = lt.member_id
             WHERE lt.status = 'borrowed' AND lt.due_date < CURDATE()"
        );
        $loans = $stmt->fetchAll();
        if ($loans === []) {
            return;
        }

        $loanIds = array_map(static fn(array $loan): int => (int)$loan['id'], $loans);
        $placeholders = implode(',', array_fill(0, count($loanIds), '?'));
        $exists = $this->db->prepare(
            "SELECT DISTINCT loan_transaction_id
             FROM notifications
             WHERE loan_transaction_id IN ({$placeholders})
               AND type = 'overdue'
               AND created_at >= CURDATE()
               AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        );
        $exists->execute($loanIds);
        $alreadyNotified = array_fill_keys(array_map('intval', $exists->fetchAll(PDO::FETCH_COLUMN)), true);

        $notes = new NotificationService($this->db);
        foreach ($loans as $loan) {
            if (isset($alreadyNotified[(int)$loan['id']])) {
                continue;
            }

            $notes->create((int)$loan['user_id'], null, 'Book overdue', 'Your copy of "' . $loan['title'] . '" is overdue.', 'overdue', (int)$loan['id'], 'loan', (int)$loan['id'], 'overdue');
            $notes->create(null, 'admin', 'Overdue book', '"' . $loan['title'] . '" is overdue.', 'overdue', (int)$loan['id'], 'loan', (int)$loan['id'], 'overdue');
        }
    }

    public function findLoan(int $id): array
    {
        $stmt = $this->db->prepare($this->loanSql() . ' WHERE lt.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('Loan not found.', 404);
        }

        return AppNormalizer::loan($row);
    }

    private function issueLocked(int $memberId, int $bookId, int $issuedBy, string $dueDate, ?int $requestId): array
    {
        $this->lockMemberForBorrowing($memberId);
        $this->assertCanBorrow($memberId);
        $this->assertNoActiveLoanForBook($memberId, $bookId);

        $book = $this->bookRow($bookId);
        if (!$book) {
            Response::error('Book not found.', 404);
        }

        $copy = (new BookService($this->db))->availableCopy($bookId);
        if (!$copy) {
            Response::error('No available copies for this book.', 409);
        }

        $copyUpdate = $this->db->prepare(
            "UPDATE book_copies
             SET status = 'borrowed', updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id AND status = 'available'"
        );
        $copyUpdate->execute(['updated_by' => $issuedBy, 'id' => (int)$copy['id']]);
        if ($copyUpdate->rowCount() !== 1) {
            Response::error('Selected copy is no longer available.', 409);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO loan_transactions
             (loan_request_id, member_id, book_id, copy_id, issued_by, borrowed_at, due_date, status, created_by, updated_by, created_at, updated_at)
             VALUES
             (:loan_request_id, :member_id, :book_id, :copy_id, :issued_by, NOW(), :due_date, "borrowed", :created_by, :updated_by, NOW(), NOW())'
        );
        $stmt->execute([
            'loan_request_id' => $requestId,
            'member_id' => $memberId,
            'book_id' => $bookId,
            'copy_id' => (int)$copy['id'],
            'issued_by' => $issuedBy,
            'due_date' => $dueDate,
            'created_by' => $issuedBy,
            'updated_by' => $issuedBy,
        ]);
        if ($stmt->rowCount() !== 1) {
            Response::error('Loan could not be created.', 409);
        }
        $loanId = (int)$this->db->lastInsertId();

        $member = $this->memberRow($memberId);
        $notes = new NotificationService($this->db);
        $notes->create((int)$member['user_id'], null, 'Book issued', 'A copy of "' . $book['title'] . '" has been issued to you. Due date: ' . $dueDate . '.', 'info', $loanId, 'loan', $loanId, 'issued');
        $notes->create(null, 'admin', 'Book issued', '"' . $book['title'] . '" was issued.', 'info', $loanId, 'loan', $loanId, 'issued');

        return $this->findLoan($loanId);
    }

    private function assertCanBorrow(int $memberId): void
    {
        $member = $this->memberRow($memberId);
        if (($member['status'] ?? '') !== 'active') {
            Response::error('Inactive members cannot borrow books.', 409);
        }

        if ($this->activeLoanCount($memberId) >= $this->maxActiveLoans()) {
            Response::error('Maximum active loan limit reached', 409);
        }

        $overdue = $this->db->prepare("SELECT id FROM loan_transactions WHERE member_id = :member_id AND status = 'borrowed' AND due_date < CURDATE() LIMIT 1");
        $overdue->execute(['member_id' => $memberId]);
        if ($overdue->fetch()) {
            Response::error('Overdue users cannot borrow again until books are returned.', 409);
        }

        if ((new FineService($this->db))->unpaidTotal($memberId) > 0) {
            Response::error('Members with unpaid fines cannot borrow until fines are settled.', 409);
        }
    }

    private function dateInput(mixed $value, string $label): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            Response::error($label . ' is required.', 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            Response::error($label . ' must use YYYY-MM-DD format.', 422);
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (!$date || $date->format('Y-m-d') !== $raw) {
            Response::error($label . ' is not a valid date.', 422);
        }

        return $raw;
    }

    private function lockMemberForBorrowing(int $memberId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM members WHERE id = :id AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute(['id' => $memberId]);
        if (!$stmt->fetch()) {
            Response::error('Member account not found.', 404);
        }
    }

    private function activeLoanCount(int $memberId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM loan_transactions WHERE member_id = :member_id AND status = 'borrowed'");
        $stmt->execute(['member_id' => $memberId]);
        return (int)$stmt->fetchColumn();
    }

    private function pendingRequestCount(int $memberId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM loan_requests WHERE member_id = :member_id AND status = 'pending'");
        $stmt->execute(['member_id' => $memberId]);
        return (int)$stmt->fetchColumn();
    }

    private function maxActiveLoans(): int
    {
        return (int)Database::config()['app']['max_active_loans'];
    }

    private function assertNoActiveLoanForBook(int $memberId, int $bookId): void
    {
        if ($this->hasActiveLoanForBook($memberId, $bookId)) {
            Response::error('This member already has an active loan for this book.', 409);
        }
    }

    private function hasActiveLoanForBook(int $memberId, int $bookId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM loan_transactions WHERE member_id = :member_id AND book_id = :book_id AND status = 'borrowed' LIMIT 1"
        );
        $stmt->execute(['member_id' => $memberId, 'book_id' => $bookId]);
        return (bool)$stmt->fetch();
    }

    private function memberForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.first_name, u.last_name, u.email
             FROM members m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.user_id = :user_id AND m.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $member = $stmt->fetch();
        if (!$member) {
            Response::error('Member account not found.', 404);
        }

        return $member;
    }

    private function memberForMemberOrUser(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*, u.first_name, u.last_name, u.email
             FROM members m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.id = :id AND m.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $member = $stmt->fetch();
        if ($member) {
            return $member;
        }

        $stmt = $this->db->prepare(
            'SELECT m.*, u.first_name, u.last_name, u.email
             FROM members m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.user_id = :id AND m.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $member = $stmt->fetch();
        if (!$member) {
            Response::error('Member not found.', 404);
        }

        return $member;
    }

    private function memberRow(int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM members WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $memberId]);
        return $stmt->fetch() ?: [];
    }

    private function bookRow(int $bookId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM books WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $bookId]);
        $book = $stmt->fetch();
        return $book ?: null;
    }

    private function lockBookForUpdate(int $bookId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM books WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $bookId]);
        $book = $stmt->fetch();
        if (!$book) {
            Response::error('Book not found.', 404);
        }

        return $book;
    }

    private function availableCopies(int $bookId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM book_copies WHERE book_id = :book_id AND status = 'available' AND deleted_at IS NULL"
        );
        $stmt->execute(['book_id' => $bookId]);
        return (int)$stmt->fetchColumn();
    }

    private function normalizedDueDate(?string $date): string
    {
        if (!$date) {
            Response::error('Invalid return date', 400);
        }

        $dateOnly = substr($date, 0, 10);
        $requested = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOnly);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$requested || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            Response::error('Invalid return date', 400);
        }

        $max = (new DateTimeImmutable('today'))->modify('+10 days');
        $min = new DateTimeImmutable('tomorrow');
        if ($requested < $min || $requested > $max) {
            Response::error('Invalid return date', 400);
        }

        return $requested->format('Y-m-d');
    }

    private function daysLate(string $dueDate, int $graceDays): int
    {
        $due = new DateTimeImmutable(substr($dueDate, 0, 10));
        $today = new DateTimeImmutable('today');
        $diff = (int)$due->diff($today)->format('%r%a');
        return max(0, $diff - $graceDays);
    }

    private function nextQueuePosition(int $bookId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(MAX(queue_position),0) + 1
             FROM reservations
             WHERE book_id = :book_id AND status IN ('pending','active','ready_for_pickup')"
        );
        $stmt->execute(['book_id' => $bookId]);
        return (int)$stmt->fetchColumn();
    }

    private function promoteNextReservation(int $bookId): void
    {
        $this->lockBookForUpdate($bookId);

        if ($this->availableCopies($bookId) <= 0) {
            return;
        }

        if ($this->readyReservationExists($bookId)) {
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT id
             FROM reservations
             WHERE book_id = :book_id AND status IN ('pending','active')
             ORDER BY queue_position, created_at
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute(['book_id' => $bookId]);
        $reservationId = (int)($stmt->fetchColumn() ?: 0);
        if ($reservationId <= 0) {
            return;
        }

        if (!$this->reservationStatusSupported('ready_for_pickup')) {
            $this->notifyReservationReadyById($reservationId);
            return;
        }

        $this->setReservationStatus($reservationId, 'ready_for_pickup', [
            'expires_at' => (new DateTimeImmutable('+3 days'))->format('Y-m-d 17:00:00'),
        ]);
        $this->notifyReservationReadyById($reservationId);
    }

    private function notifyReservationReadyById(int $reservationId): void
    {
        $stmt = $this->db->prepare(
            "SELECT r.*, m.user_id, b.title
             FROM reservations r
             INNER JOIN members m ON m.id = r.member_id
             INNER JOIN books b ON b.id = r.book_id
             WHERE r.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $reservationId]);
        $reservation = $stmt->fetch();
        if (!$reservation) {
            return;
        }

        $notes = new NotificationService($this->db);
        $notes->create((int)$reservation['user_id'], null, 'Reserved book available', 'Good news! "' . $reservation['title'] . '" is now available.', 'info', null, 'reservation', (int)$reservation['id'], 'ready');
        $notes->create(null, 'admin', 'Reservation ready', '"' . $reservation['title'] . '" is now available for a reserved member.', 'info', null, 'reservation', (int)$reservation['id'], 'ready');
    }

    private function readyReservationExists(int $bookId): bool
    {
        if (!$this->reservationStatusSupported('ready_for_pickup')) {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT id FROM reservations
             WHERE book_id = :book_id AND status = 'ready_for_pickup'
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute(['book_id' => $bookId]);
        return (bool)$stmt->fetch();
    }

    private function reservationRowForUpdate(int $reservationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM reservations WHERE id = :id LIMIT 1 FOR UPDATE"
        );
        $stmt->execute(['id' => $reservationId]);
        $reservation = $stmt->fetch();
        if (!$reservation) {
            Response::error('Reservation not found.', 404);
        }

        return $reservation;
    }

    private function setReservationStatus(int $reservationId, string $status, array $extra = []): void
    {
        if (!$this->reservationStatusSupported($status)) {
            Response::error('Reservation status migration is required before using this action.', 409);
        }

        $sets = ['status = :status', 'updated_at = NOW()'];
        $params = ['status' => $status, 'id' => $reservationId];
        $timestampColumns = $this->reservationTimestampColumns();
        $timestampByStatus = [
            'ready_for_pickup' => 'ready_at',
            'completed' => 'fulfilled_at',
            'cancelled' => 'cancelled_at',
            'expired' => 'expired_at',
        ];

        $column = $timestampByStatus[$status] ?? null;
        if ($column && in_array($column, $timestampColumns, true)) {
            $sets[] = "{$column} = NOW()";
        }

        if (array_key_exists('expires_at', $extra)) {
            $sets[] = 'expires_at = :expires_at';
            $params['expires_at'] = $extra['expires_at'];
        }

        $stmt = $this->db->prepare('UPDATE reservations SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        if ($stmt->rowCount() !== 1) {
            Response::error('Reservation state could not be changed.', 409);
        }
    }

    private function renumberReservationQueue(int $bookId): void
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM reservations
             WHERE book_id = :book_id AND status IN ('pending','active','ready_for_pickup')
             ORDER BY queue_position, created_at"
        );
        $stmt->execute(['book_id' => $bookId]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $update = $this->db->prepare('UPDATE reservations SET queue_position = :position, updated_at = NOW() WHERE id = :id');
        foreach ($ids as $index => $id) {
            $update->execute(['position' => $index + 1, 'id' => $id]);
        }
    }

    private function reservationTimestampColumns(): array
    {
        static $columns = null;
        if ($columns !== null) {
            return $columns;
        }

        $columns = [];
        foreach ($this->db->query('DESCRIBE reservations')->fetchAll() as $row) {
            $columns[] = (string)$row['Field'];
        }

        return $columns;
    }

    private function reservationStatusSupported(string $status): bool
    {
        static $type = null;
        if ($type === null) {
            $stmt = $this->db->query("SHOW COLUMNS FROM reservations LIKE 'status'");
            $row = $stmt->fetch();
            $type = (string)($row['Type'] ?? '');
        }

        return str_contains($type, "'" . $status . "'");
    }

    private function findRequest(int $id): array
    {
        $stmt = $this->db->prepare($this->requestSql() . ' WHERE lr.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('Borrow request not found.', 404);
        }

        return AppNormalizer::request($row);
    }

    private function findReservation(int $id): array
    {
        $stmt = $this->db->prepare($this->reservationSql() . ' WHERE r.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('Reservation not found.', 404);
        }

        return AppNormalizer::reservation($row);
    }

    private function loanSql(): string
    {
        return 'SELECT lt.*, m.user_id, b.title, b.late_fee_per_day
                FROM loan_transactions lt
                INNER JOIN members m ON m.id = lt.member_id
                INNER JOIN books b ON b.id = lt.book_id';
    }

    private function requestSql(): string
    {
        return 'SELECT lr.*, m.user_id, b.title AS book_title
                FROM loan_requests lr
                INNER JOIN members m ON m.id = lr.member_id
                INNER JOIN books b ON b.id = lr.book_id';
    }

    private function reservationSql(): string
    {
        return 'SELECT r.*, m.user_id, b.title AS book_title
                FROM reservations r
                INNER JOIN members m ON m.id = r.member_id
                INNER JOIN books b ON b.id = r.book_id';
    }
}
