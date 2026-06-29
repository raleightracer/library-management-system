<?php
declare(strict_types=1);

final class FineService extends BaseModel
{
    private const RULE_TYPES = ['overdue', 'lost', 'damaged', 'manual'];

    public function list(array $currentUser): array
    {
        if (($currentUser['role_slug'] ?? '') === 'admin') {
            $stmt = $this->db->query($this->fineSql() . ' ORDER BY f.assessed_at DESC, f.id DESC');
        } else {
            $stmt = $this->db->prepare($this->fineSql() . ' WHERE f.member_id = :member_id ORDER BY f.assessed_at DESC, f.id DESC');
            $stmt->execute(['member_id' => (int)$currentUser['member_id']]);
        }

        return array_map([AppNormalizer::class, 'fine'], $stmt->fetchAll());
    }

    public function pay(int $fineId, int $paidBy): array
    {
        $this->db->beginTransaction();
        try {
            $this->lockPayableFine($fineId);
            $this->assertNoPendingPayment($fineId);

            $stmt = $this->db->prepare(
                "UPDATE fines
                 SET status = 'paid', paid_at = NOW(), paid_by = :paid_by, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id AND status = 'unpaid'"
            );
            $stmt->execute(['paid_by' => $paidBy, 'updated_by' => $paidBy, 'id' => $fineId]);
            if ($stmt->rowCount() !== 1) {
                Response::error('This fine is already paid or no longer payable.', 409);
            }

            (new AuditService($this->db))->log($paidBy, 'pay', 'fines', $fineId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->find($fineId);
    }

    public function waive(int $fineId, int $waivedBy, ?string $reason = null): array
    {
        $this->db->beginTransaction();
        try {
            $this->lockPayableFine($fineId);
            $this->assertNoPendingPayment($fineId);

            $stmt = $this->db->prepare(
                "UPDATE fines
                 SET status = 'waived', paid_at = NULL, paid_by = NULL, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id AND status = 'unpaid'"
            );
            $stmt->execute(['updated_by' => $waivedBy, 'id' => $fineId]);
            if ($stmt->rowCount() !== 1) {
                Response::error('This fine is already paid or no longer payable.', 409);
            }

            (new AuditService($this->db))->log($waivedBy, 'waive', 'fines', $fineId, ['reason' => $reason ?: 'not specified']);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->find($fineId);
    }

    public function adjust(int $fineId, float $amount, int $adjustedBy): array
    {
        if ($amount < 0) {
            Response::error('Fine amount cannot be negative.', 422);
        }

        $this->db->beginTransaction();
        try {
            $before = $this->lockPayableFine($fineId);
            $this->assertNoPendingPayment($fineId);

            $stmt = $this->db->prepare(
                "UPDATE fines
                 SET amount = :amount, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id AND status = 'unpaid'"
            );
            $stmt->execute(['amount' => $amount, 'updated_by' => $adjustedBy, 'id' => $fineId]);

            (new AuditService($this->db))->log($adjustedBy, 'adjust', 'fines', $fineId, [
                'from' => $before['amount'] ?? null,
                'to' => $amount,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->find($fineId);
    }

    public function createFine(
        int $memberId,
        ?int $loanTransactionId,
        ?int $bookId,
        string $type,
        float $amount,
        string $reason,
        ?int $createdBy = null
    ): int {
        if ($loanTransactionId !== null && in_array($type, ['lost', 'damaged'], true)) {
            $duplicate = $this->db->prepare(
                "SELECT id FROM fines
                 WHERE loan_transaction_id = :loan_transaction_id
                   AND fine_type IN ('lost','damaged')
                   AND status IN ('unpaid','paid')
                 LIMIT 1"
            );
            $duplicate->execute(['loan_transaction_id' => $loanTransactionId]);
            if ($duplicate->fetch()) {
                Response::error('A lost or damaged item fine already exists for this loan.', 409);
            }
        }

        $ruleId = $this->ruleId($type);
        $stmt = $this->db->prepare(
            'INSERT INTO fines
             (member_id, loan_transaction_id, book_id, fine_rule_id, fine_type, amount, reason, status,
              assessed_at, created_by, updated_by, created_at, updated_at)
             VALUES
             (:member_id, :loan_transaction_id, :book_id, :fine_rule_id, :fine_type, :amount, :reason, "unpaid",
              NOW(), :created_by, :updated_by, NOW(), NOW())'
        );
        $stmt->execute([
            'member_id' => $memberId,
            'loan_transaction_id' => $loanTransactionId,
            'book_id' => $bookId,
            'fine_rule_id' => $ruleId,
            'fine_type' => $type,
            'amount' => $amount,
            'reason' => $reason,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function unpaidTotal(int $memberId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM fines WHERE member_id = :member_id AND status = 'unpaid'");
        $stmt->execute(['member_id' => $memberId]);
        return (float)$stmt->fetchColumn();
    }

    public function find(int $fineId): array
    {
        $stmt = $this->db->prepare($this->fineSql() . ' WHERE f.id = :id LIMIT 1');
        $stmt->execute(['id' => $fineId]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('Fine not found.', 404);
        }

        return AppNormalizer::fine($row);
    }

    public function listFineRules(): array
    {
        $stmt = $this->db->query(
            'SELECT id, book_id, name, fine_type, amount_per_day, grace_days, default_amount,
                    use_book_price, is_active, created_at, updated_at
             FROM fine_rules
             WHERE deleted_at IS NULL
             ORDER BY is_active DESC, fine_type, id DESC'
        );

        return array_map([$this, 'normalizeFineRule'], $stmt->fetchAll());
    }

    public function createFineRule(array $data, int $userId): array
    {
        $rule = $this->validateFineRuleData($data);
        $stmt = $this->db->prepare(
            'INSERT INTO fine_rules
             (book_id, name, fine_type, amount_per_day, grace_days, default_amount, use_book_price,
              is_active, created_by, updated_by, created_at, updated_at)
             VALUES
             (:book_id, :name, :fine_type, :amount_per_day, :grace_days, :default_amount, :use_book_price,
              :is_active, :created_by, :updated_by, NOW(), NOW())'
        );
        $stmt->execute([
            ...$rule,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $id = (int)$this->db->lastInsertId();
        (new AuditService($this->db))->log($userId, 'create', 'fine_rules', $id);
        return $this->findFineRule($id);
    }

    public function updateFineRule(int $id, array $data, int $userId): array
    {
        $this->findFineRule($id);
        $rule = $this->validateFineRuleData($data);
        $stmt = $this->db->prepare(
            'UPDATE fine_rules
             SET book_id = :book_id,
                 name = :name,
                 fine_type = :fine_type,
                 amount_per_day = :amount_per_day,
                 grace_days = :grace_days,
                 default_amount = :default_amount,
                 use_book_price = :use_book_price,
                 is_active = :is_active,
                 updated_by = :updated_by,
                 updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            ...$rule,
            'updated_by' => $userId,
            'id' => $id,
        ]);

        (new AuditService($this->db))->log($userId, 'update', 'fine_rules', $id);
        return $this->findFineRule($id);
    }

    public function disableFineRule(int $id, int $userId): array
    {
        $this->findFineRule($id);
        $stmt = $this->db->prepare(
            'UPDATE fine_rules
             SET is_active = 0, updated_by = :updated_by, deleted_by = :deleted_by, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'updated_by' => $userId,
            'deleted_by' => $userId,
            'id' => $id,
        ]);

        (new AuditService($this->db))->log($userId, 'disable', 'fine_rules', $id);
        return $this->findFineRule($id);
    }

    public function overdueRule(): array
    {
        return $this->rule('overdue') ?: [
            'amount_per_day' => 20,
            'grace_days' => 1,
        ];
    }

    public function replacementAmount(array $book, ?float $override = null): float
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        if (!empty($book['replacement_value']) && (float)$book['replacement_value'] > 0) {
            return (float)$book['replacement_value'];
        }

        if (!empty($book['price']) && (float)$book['price'] > 0) {
            return (float)$book['price'];
        }

        $rule = $this->rule('lost');
        return (float)($rule['default_amount'] ?? 500);
    }

    private function rule(string $type): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM fine_rules WHERE fine_type = :type AND is_active = 1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['type' => $type]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function ruleId(string $type): ?int
    {
        $rule = $this->rule($type);
        return $rule ? (int)$rule['id'] : null;
    }

    private function lockPayableFine(int $fineId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM fines WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $fineId]);
        $fine = $stmt->fetch();
        if (!$fine) {
            Response::error('Fine not found.', 404);
        }
        if (($fine['status'] ?? '') !== 'unpaid') {
            Response::error('This fine is already paid or no longer payable.', 409);
        }

        return $fine;
    }

    private function assertNoPendingPayment(int $fineId): void
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM payments
             WHERE fine_id = :fine_id AND status = 'pending'
             LIMIT 1"
        );
        $stmt->execute(['fine_id' => $fineId]);
        if ($stmt->fetch()) {
            Response::error('This fine has a pending online payment and cannot be changed yet.', 409);
        }
    }

    private function findFineRule(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, book_id, name, fine_type, amount_per_day, grace_days, default_amount,
                    use_book_price, is_active, created_at, updated_at
             FROM fine_rules
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('Fine rule not found.', 404);
        }

        return $this->normalizeFineRule($row);
    }

    private function validateFineRuleData(array $data): array
    {
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            Response::error('Rule name is required.', 422, ['name' => 'This field is required.']);
        }

        $type = trim((string)($data['fine_type'] ?? $data['type'] ?? ''));
        if (!in_array($type, self::RULE_TYPES, true)) {
            Response::error('Fine rule type is invalid.', 422, ['fine_type' => 'Choose a valid fine type.']);
        }

        $amountValue = $data['amount'] ?? ($type === 'overdue' ? ($data['amount_per_day'] ?? null) : ($data['default_amount'] ?? null));
        if ($amountValue === null || $amountValue === '' || !is_numeric($amountValue)) {
            Response::error('Fine rule amount is required.', 422, ['amount' => 'Enter a valid amount.']);
        }

        $amount = (float)$amountValue;
        if ($amount < 0) {
            Response::error('Fine rule amount cannot be negative.', 422, ['amount' => 'Amount must be zero or greater.']);
        }

        $graceDaysValue = $data['grace_days'] ?? 0;
        if (!is_numeric($graceDaysValue) || (int)$graceDaysValue < 0) {
            Response::error('Grace days must be zero or greater.', 422, ['grace_days' => 'Enter a valid number of days.']);
        }

        $bookId = $data['book_id'] ?? null;
        if ($bookId === '') {
            $bookId = null;
        }

        return [
            'book_id' => $bookId !== null ? (int)$bookId : null,
            'name' => $name,
            'fine_type' => $type,
            'amount_per_day' => $type === 'overdue' ? $amount : null,
            'grace_days' => (int)$graceDaysValue,
            'default_amount' => $type === 'overdue' ? null : $amount,
            'use_book_price' => !empty($data['use_book_price']) ? 1 : 0,
            'is_active' => isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1,
        ];
    }

    private function normalizeFineRule(array $row): array
    {
        $type = (string)($row['fine_type'] ?? '');
        $amount = $type === 'overdue' ? ($row['amount_per_day'] ?? 0) : ($row['default_amount'] ?? 0);

        return [
            'id' => (string)$row['id'],
            'bookId' => isset($row['book_id']) ? (string)$row['book_id'] : null,
            'name' => $row['name'] ?? '',
            'type' => $type,
            'fineType' => $type,
            'amount' => (float)$amount,
            'amountPerDay' => isset($row['amount_per_day']) ? (float)$row['amount_per_day'] : null,
            'graceDays' => (int)($row['grace_days'] ?? 0),
            'defaultAmount' => isset($row['default_amount']) ? (float)$row['default_amount'] : null,
            'useBookPrice' => (bool)($row['use_book_price'] ?? false),
            'isActive' => (bool)($row['is_active'] ?? false),
            'status' => !empty($row['is_active']) ? 'active' : 'disabled',
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private function fineSql(): string
    {
        return "SELECT f.*, m.user_id, u.first_name, u.last_name, b.title AS book_title
                FROM fines f
                INNER JOIN members m ON m.id = f.member_id
                INNER JOIN users u ON u.id = m.user_id
                LEFT JOIN books b ON b.id = f.book_id";
    }
}
