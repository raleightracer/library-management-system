<?php
declare(strict_types=1);

final class ReportService extends BaseModel
{
    private const LOAN_REPORT_TYPES = ['issued', 'overdue'];
    private const FINE_REPORT_STATUSES = ['all', 'paid', 'unpaid', 'collected', 'outstanding'];

    public function issuedBooks(?string $from = null, ?string $to = null): array
    {
        return $this->runLoanReport("lt.status = 'borrowed'", $from, $to);
    }

    public function overdueBooks(): array
    {
        $stmt = $this->db->query(
            "SELECT lt.id, b.title, u.first_name, u.last_name, lt.borrowed_at, lt.due_date,
                    DATEDIFF(CURDATE(), lt.due_date) AS days_overdue
             FROM loan_transactions lt
             INNER JOIN books b ON b.id = lt.book_id
             INNER JOIN members m ON m.id = lt.member_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE lt.status = 'borrowed' AND lt.due_date < CURDATE()
             ORDER BY lt.due_date ASC"
        );
        return $stmt->fetchAll();
    }

    public function returnedBooks(?string $from = null, ?string $to = null): array
    {
        return $this->runLoanReport("lt.status = 'returned'", $from, $to, 'lt.returned_at');
    }

    public function finesCollected(?string $from = null, ?string $to = null): array
    {
        $where = ["f.status = 'paid'"];
        $params = [];
        $this->dateRange($where, $params, 'f.paid_at', $from, $to);

        $stmt = $this->db->prepare(
            'SELECT f.id, f.amount, f.reason, f.paid_at, b.title, u.first_name, u.last_name
             FROM fines f
             INNER JOIN members m ON m.id = f.member_id
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN books b ON b.id = f.book_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY f.paid_at DESC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'total' => array_reduce($rows, static fn(float $sum, array $row): float => $sum + (float)$row['amount'], 0.0),
            'rows' => $rows,
        ];
    }

    public function loanDetailReport(string $type, array $filters = []): array
    {
        if (!in_array($type, self::LOAN_REPORT_TYPES, true)) {
            Response::error('Invalid loan report type.', 400);
        }

        $where = [];
        $params = [];
        $dateField = 'lt.borrowed_at';

        if ($type === 'issued') {
            $where[] = '1 = 1';
        } else {
            $where[] = "lt.status = 'borrowed'";
            $where[] = 'lt.due_date < CURDATE()';
        }

        $this->dateRange($where, $params, $dateField, $filters['from'] ?? null, $filters['to'] ?? null);
        $this->userFilter($where, $params, $filters['user'] ?? null);

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $where[] = 'lt.status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->db->prepare(
            'SELECT lt.id, b.title, b.isbn, u.first_name, u.last_name, u.email, u.username,
                    lt.borrowed_at, lt.due_date, lt.returned_at, lt.status,
                    CASE WHEN lt.status = "borrowed" AND lt.due_date < CURDATE()
                         THEN DATEDIFF(CURDATE(), lt.due_date)
                         ELSE 0
                    END AS days_overdue
             FROM loan_transactions lt
             INNER JOIN books b ON b.id = lt.book_id
             INNER JOIN members m ON m.id = lt.member_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $dateField . ' DESC, lt.id DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function fineDetailReport(array $filters = []): array
    {
        $where = [];
        $params = [];
        $status = trim((string)($filters['status'] ?? 'all'));
        if (!in_array($status, self::FINE_REPORT_STATUSES, true)) {
            Response::error('Invalid fine report status.', 400);
        }

        if (in_array($status, ['paid', 'collected'], true)) {
            $where[] = "f.status = 'paid'";
            $dateField = 'f.paid_at';
        } elseif (in_array($status, ['unpaid', 'outstanding'], true)) {
            $where[] = "f.status = 'unpaid'";
            $dateField = 'f.assessed_at';
        } else {
            $where[] = '1 = 1';
            $dateField = 'f.assessed_at';
        }

        $this->dateRange($where, $params, $dateField, $filters['from'] ?? null, $filters['to'] ?? null);
        $this->userFilter($where, $params, $filters['user'] ?? null);

        $stmt = $this->db->prepare(
            'SELECT f.id, f.fine_type, f.amount, f.reason, f.status, f.assessed_at, f.paid_at,
                    b.title, u.first_name, u.last_name, u.email, u.username
             FROM fines f
             INNER JOIN members m ON m.id = f.member_id
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN books b ON b.id = f.book_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $dateField . ' DESC, f.id DESC'
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return [
            'paid_total' => array_reduce($rows, static fn(float $sum, array $row): float => $sum + (($row['status'] ?? '') === 'paid' ? (float)$row['amount'] : 0.0), 0.0),
            'unpaid_total' => array_reduce($rows, static fn(float $sum, array $row): float => $sum + (($row['status'] ?? '') === 'unpaid' ? (float)$row['amount'] : 0.0), 0.0),
            'rows' => $rows,
        ];
    }

    public function activeMembers(): array
    {
        $stmt = $this->db->query(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.username,
                    COUNT(CASE WHEN lt.status = 'borrowed' THEN 1 END) AS active_loans,
                    COUNT(lt.id) AS total_loans
             FROM members m
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN loan_transactions lt ON lt.member_id = m.id
             WHERE m.deleted_at IS NULL AND m.status = 'active'
             GROUP BY u.id
             ORDER BY active_loans DESC, total_loans DESC, u.last_name"
        );
        return $stmt->fetchAll();
    }

    public function popularBooks(): array
    {
        $stmt = $this->db->query(
            'SELECT b.id, b.title, COUNT(lt.id) AS borrow_count
             FROM books b
             LEFT JOIN loan_transactions lt ON lt.book_id = b.id
             WHERE b.deleted_at IS NULL
             GROUP BY b.id
             ORDER BY borrow_count DESC, b.title
             LIMIT 20'
        );
        return $stmt->fetchAll();
    }

    public function all(?string $from = null, ?string $to = null): array
    {
        return [
            'issued_books' => $this->issuedBooks($from, $to),
            'overdue_books' => $this->overdueBooks(),
            'returned_books' => $this->returnedBooks($from, $to),
            'fines_collected' => $this->finesCollected($from, $to),
            'active_members' => $this->activeMembers(),
            'popular_books' => $this->popularBooks(),
        ];
    }

    private function runLoanReport(string $condition, ?string $from = null, ?string $to = null, string $dateField = 'lt.borrowed_at'): array
    {
        $where = [$condition];
        $params = [];
        $this->dateRange($where, $params, $dateField, $from, $to);

        $stmt = $this->db->prepare(
            'SELECT lt.id, b.title, u.first_name, u.last_name, lt.borrowed_at, lt.due_date, lt.returned_at, lt.status
             FROM loan_transactions lt
             INNER JOIN books b ON b.id = lt.book_id
             INNER JOIN members m ON m.id = lt.member_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $dateField . ' DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function dateRange(array &$where, array &$params, string $field, ?string $from, ?string $to): void
    {
        if ($from) {
            $where[] = "{$field} >= :from_date";
            $params['from_date'] = substr($from, 0, 10) . ' 00:00:00';
        }
        if ($to) {
            $where[] = "{$field} <= :to_date";
            $params['to_date'] = substr($to, 0, 10) . ' 23:59:59';
        }
    }

    private function userFilter(array &$where, array &$params, mixed $user): void
    {
        $user = trim((string)$user);
        if ($user === '') {
            return;
        }

        $where[] = '(CAST(u.id AS CHAR) = :user_id_query
            OR CAST(m.id AS CHAR) = :member_id_query
            OR u.username LIKE :username_like
            OR u.email LIKE :email_like
            OR CONCAT(u.first_name, " ", u.last_name) LIKE :name_like)';
        $params['user_id_query'] = $user;
        $params['member_id_query'] = $user;
        $params['username_like'] = '%' . $user . '%';
        $params['email_like'] = '%' . $user . '%';
        $params['name_like'] = '%' . $user . '%';
    }
}
