<?php
declare(strict_types=1);

final class NotificationService extends BaseModel
{
    private static bool $ensured = false;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
    }

    public function create(
        ?int $userId,
        ?string $targetRole,
        string $title,
        string $message,
        string $type = 'info',
        ?int $loanTransactionId = null,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?string $actionType = null
    ): void
    {
        if ($userId !== null && !$this->allowsInAppNotification($userId, $relatedEntityType, $actionType)) {
            return;
        }
        if ($targetRole === 'staff') {
            $targetRole = 'member';
            $actionType = $actionType ? $actionType . '|staff_only' : 'staff_only';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO notifications
             (user_id, target_role, loan_transaction_id, related_entity_type, related_entity_id, action_type, title, message, type)
             VALUES
             (:user_id, :target_role, :loan_transaction_id, :related_entity_type, :related_entity_id, :action_type, :title, :message, :type)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'target_role' => $targetRole,
            'loan_transaction_id' => $loanTransactionId,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id' => $relatedEntityId,
            'action_type' => $actionType,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);

        $this->sendEmailGracefully($userId, $title, $message);
    }

    public function listForCurrentUser(array $currentUser): array
    {
        [$where, $params] = $this->visibilityPredicate($currentUser);
        $stmt = $this->db->prepare(
            "SELECT * FROM notifications
             WHERE deleted_at IS NULL
               AND {$where}
             ORDER BY created_at DESC"
        );

        $stmt->execute($params);
        return array_map([AppNormalizer::class, 'notification'], $stmt->fetchAll());
    }

    public function markAllRead(array $currentUser): void
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications
             SET is_read = 1, read_at = NOW()
             WHERE deleted_at IS NULL
               AND user_id = :user_id"
        );
        $stmt->execute(['user_id' => (int)$currentUser['id']]);
    }

    public function markSingleRead(int $notificationId, array $currentUser): void
    {
        $row = $this->accessibleNotification($notificationId, $currentUser);
        if ((int)($row['user_id'] ?? 0) !== (int)$currentUser['id']) {
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE notifications
             SET is_read = 1, read_at = COALESCE(read_at, NOW())
             WHERE id = :id"
        );
        $stmt->execute(['id' => $notificationId]);
    }

    public function markSingleUnread(int $notificationId, array $currentUser): void
    {
        $row = $this->accessibleNotification($notificationId, $currentUser);
        if ((int)($row['user_id'] ?? 0) !== (int)$currentUser['id']) {
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE notifications
             SET is_read = 0, read_at = NULL
             WHERE id = :id
               AND user_id = :user_id
               AND deleted_at IS NULL"
        );
        $stmt->execute([
            'id' => $notificationId,
            'user_id' => (int)$currentUser['id'],
        ]);
    }

    public function deleteSingle(int $notificationId, array $currentUser): void
    {
        $row = $this->accessibleNotification($notificationId, $currentUser);
        if ((int)($row['user_id'] ?? 0) !== (int)$currentUser['id']) {
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE notifications
             SET deleted_at = NOW()
             WHERE id = :id
               AND user_id = :user_id
               AND deleted_at IS NULL"
        );
        $stmt->execute([
            'id' => $notificationId,
            'user_id' => (int)$currentUser['id'],
        ]);
    }

    public function clearForCurrentUser(array $currentUser): int
    {
        $stmt = $this->db->prepare(
            "UPDATE notifications
             SET deleted_at = NOW()
             WHERE user_id = :user_id
               AND deleted_at IS NULL"
        );
        $stmt->execute(['user_id' => (int)$currentUser['id']]);

        return $stmt->rowCount();
    }

    private function assertAccessible(int $notificationId, array $currentUser): void
    {
        $this->accessibleNotification($notificationId, $currentUser);
    }

    private function accessibleNotification(int $notificationId, array $currentUser): array
    {
        [$where, $params] = $this->visibilityPredicate($currentUser);
        $check = $this->db->prepare(
            "SELECT * FROM notifications
             WHERE id = :id
               AND deleted_at IS NULL
               AND {$where}
             LIMIT 1"
        );

        $check->execute(['id' => $notificationId] + $params);
        $row = $check->fetch();
        if (!$row) {
            Response::error('Notification not found.', 404);
        }

        return $row;
    }

    private function visibilityPredicate(array $currentUser): array
    {
        $userId = (int)$currentUser['id'];
        $roleSlug = (string)($currentUser['role_slug'] ?? 'member');
        $isAdmin = $roleSlug === 'admin';
        $isStaff = $this->isStaffMember($currentUser);
        $staffOnly = "(COALESCE(action_type, '') LIKE '%staff_only%' OR related_entity_type = 'staff')";

        if ($isAdmin) {
            return [
                "(user_id = :user_id OR (user_id IS NULL AND target_role IN ('admin', 'both') AND NOT {$staffOnly}))",
                ['user_id' => $userId],
            ];
        }

        return [
            "(user_id = :user_id OR (user_id IS NULL AND target_role IN ('member', 'both') AND (:is_staff = 1 OR NOT {$staffOnly})))",
            ['user_id' => $userId, 'is_staff' => $isStaff ? 1 : 0],
        ];
    }

    private function isStaffMember(array $currentUser): bool
    {
        if (($currentUser['member_type_slug'] ?? null) === 'staff' || ($currentUser['memberType'] ?? null) === 'staff') {
            return true;
        }

        $stmt = $this->db->prepare(
            'SELECT mt.slug
             FROM members m
             INNER JOIN member_types mt ON mt.id = m.member_type_id
             WHERE m.user_id = :user_id AND m.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['user_id' => (int)$currentUser['id']]);

        return $stmt->fetchColumn() === 'staff';
    }

    private function assertDirectUserNotification(int $notificationId, array $currentUser): void
    {
        $stmt = $this->db->prepare(
            "SELECT id, user_id, target_role, deleted_at
             FROM notifications
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $notificationId]);
        $row = $stmt->fetch();

        if (!$row || $row['deleted_at'] !== null) {
            Response::error('Notification not found.', 404);
        }
        if ((int)($row['user_id'] ?? 0) !== (int)$currentUser['id']) {
            Response::error('Shared notifications cannot be deleted without per-user notification receipts.', 409);
        }
    }

    private function ensureSchema(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        if (!$this->notificationColumnExists('deleted_at')) {
            $this->db->exec('ALTER TABLE notifications ADD COLUMN deleted_at DATETIME NULL AFTER sent_email_at');
        }
        if (!$this->notificationColumnExists('related_entity_type')) {
            $this->db->exec('ALTER TABLE notifications ADD COLUMN related_entity_type VARCHAR(40) NULL AFTER loan_transaction_id');
        }
        if (!$this->notificationColumnExists('related_entity_id')) {
            $this->db->exec('ALTER TABLE notifications ADD COLUMN related_entity_id INT NULL AFTER related_entity_type');
        }
        if (!$this->notificationColumnExists('action_type')) {
            $this->db->exec('ALTER TABLE notifications ADD COLUMN action_type VARCHAR(60) NULL AFTER related_entity_id');
        }
        if (!$this->notificationColumnExists('idx_notifications_related', true)) {
            $this->db->exec('ALTER TABLE notifications ADD INDEX idx_notifications_related (related_entity_type, related_entity_id)');
        }
        $this->db->exec(
            "UPDATE notifications
             SET related_entity_type = 'loan', related_entity_id = loan_transaction_id
             WHERE loan_transaction_id IS NOT NULL
               AND related_entity_type IS NULL
               AND related_entity_id IS NULL"
        );
    }

    private function notificationColumnExists(string $name, bool $index = false): bool
    {
        $column = $index ? 'STATISTICS' : 'COLUMNS';
        $nameColumn = $index ? 'INDEX_NAME' : 'COLUMN_NAME';
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.{$column}
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'notifications'
               AND {$nameColumn} = :name"
        );
        $stmt->execute(['name' => $name]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function allowsInAppNotification(int $userId, ?string $relatedEntityType, ?string $actionType): bool
    {
        $preferenceKey = $this->preferenceKeyFor($relatedEntityType, $actionType);
        if ($preferenceKey === null) {
            return true;
        }

        $preferences = (new UserPreferenceService($this->db))->getForUser($userId);
        return (bool)($preferences[$preferenceKey] ?? true);
    }

    private function preferenceKeyFor(?string $relatedEntityType, ?string $actionType): ?string
    {
        $entity = strtolower((string)$relatedEntityType);
        $action = strtolower((string)$actionType);

        if ($entity === 'fine') {
            return 'fines_payment_notices';
        }
        if ($entity === 'loan' && in_array($action, ['overdue'], true)) {
            return 'overdue_alerts';
        }
        if ($entity === 'loan' && in_array($action, ['due_reminder', 'due_reminders'], true)) {
            return 'due_reminders';
        }
        if (in_array($entity, ['loan', 'reservation', 'borrow_request'], true)) {
            return 'transaction_alerts';
        }
        if ($entity === 'book' && in_array($action, ['created', 'new_arrival'], true)) {
            return 'new_arrivals';
        }
        if ($action === 'recommendation') {
            return 'recommendations';
        }
        if ($action === 'marketing') {
            return 'marketing_emails';
        }

        return null;
    }

    private function sendEmailGracefully(?int $userId, string $subject, string $message): void
    {
        $config = Database::config()['app'];
        if (!$userId || empty($config['email_enabled'])) {
            return;
        }

        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $email = $stmt->fetchColumn();
        if (!$email) {
            return;
        }

        try {
            @mail((string)$email, $subject, $message, 'From: ' . $config['email_from']);
        } catch (Throwable) {
            // Local XAMPP email is often not configured; database notifications remain authoritative.
        }
    }
}
