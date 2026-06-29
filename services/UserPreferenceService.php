<?php
declare(strict_types=1);

final class UserPreferenceService extends BaseModel
{
    private const DEFAULTS = [
        'transaction_alerts' => true,
        'due_reminders' => true,
        'overdue_alerts' => true,
        'fines_payment_notices' => true,
        'email_notifications' => true,
        'book_reminders' => true,
        'due_date_alerts' => true,
        'new_arrivals' => false,
        'recommendations' => true,
        'marketing_emails' => false,
    ];

    private static bool $ensured = false;

    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureTable();
    }

    public function getForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_preferences WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            return self::DEFAULTS;
        }

        $preferences = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $preferences[$key] = (bool)($row[$key] ?? self::DEFAULTS[$key]);
        }

        return $preferences;
    }

    public function updateForUser(int $userId, array $input): array
    {
        $current = $this->getForUser($userId);
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $input)) {
                $current[$key] = $this->toBool($input[$key]);
            }
        }

        $stmt = $this->db->prepare(
            'INSERT INTO user_preferences
               (user_id, transaction_alerts, due_reminders, overdue_alerts, fines_payment_notices,
                email_notifications, book_reminders, due_date_alerts, new_arrivals, recommendations, marketing_emails, created_at, updated_at)
             VALUES
               (:user_id, :transaction_alerts, :due_reminders, :overdue_alerts, :fines_payment_notices,
                :email_notifications, :book_reminders, :due_date_alerts, :new_arrivals, :recommendations, :marketing_emails, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               transaction_alerts = VALUES(transaction_alerts),
               due_reminders = VALUES(due_reminders),
               overdue_alerts = VALUES(overdue_alerts),
               fines_payment_notices = VALUES(fines_payment_notices),
               email_notifications = VALUES(email_notifications),
               book_reminders = VALUES(book_reminders),
               due_date_alerts = VALUES(due_date_alerts),
               new_arrivals = VALUES(new_arrivals),
               recommendations = VALUES(recommendations),
               marketing_emails = VALUES(marketing_emails),
               updated_at = NOW()'
        );

        $stmt->execute([
            'user_id' => $userId,
            'transaction_alerts' => $current['transaction_alerts'] ? 1 : 0,
            'due_reminders' => $current['due_reminders'] ? 1 : 0,
            'overdue_alerts' => $current['overdue_alerts'] ? 1 : 0,
            'fines_payment_notices' => $current['fines_payment_notices'] ? 1 : 0,
            'email_notifications' => $current['email_notifications'] ? 1 : 0,
            'book_reminders' => $current['book_reminders'] ? 1 : 0,
            'due_date_alerts' => $current['due_date_alerts'] ? 1 : 0,
            'new_arrivals' => $current['new_arrivals'] ? 1 : 0,
            'recommendations' => $current['recommendations'] ? 1 : 0,
            'marketing_emails' => $current['marketing_emails'] ? 1 : 0,
        ]);

        return $this->getForUser($userId);
    }

    private function ensureTable(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS user_preferences (
              id INT AUTO_INCREMENT PRIMARY KEY,
              user_id INT NOT NULL UNIQUE,
              transaction_alerts TINYINT(1) NOT NULL DEFAULT 1,
              due_reminders TINYINT(1) NOT NULL DEFAULT 1,
              overdue_alerts TINYINT(1) NOT NULL DEFAULT 1,
              fines_payment_notices TINYINT(1) NOT NULL DEFAULT 1,
              email_notifications TINYINT(1) NOT NULL DEFAULT 1,
              book_reminders TINYINT(1) NOT NULL DEFAULT 1,
              due_date_alerts TINYINT(1) NOT NULL DEFAULT 1,
              new_arrivals TINYINT(1) NOT NULL DEFAULT 0,
              recommendations TINYINT(1) NOT NULL DEFAULT 1,
              marketing_emails TINYINT(1) NOT NULL DEFAULT 0,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              INDEX idx_user_preferences_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        foreach ([
            'transaction_alerts' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER user_id',
            'due_reminders' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER transaction_alerts',
            'overdue_alerts' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER due_reminders',
            'fines_payment_notices' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER overdue_alerts',
        ] as $column => $definition) {
            if (!$this->columnExists($column)) {
                $this->db->exec("ALTER TABLE user_preferences ADD COLUMN {$column} {$definition}");
            }
        }
    }

    private function columnExists(string $name): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "user_preferences"
               AND COLUMN_NAME = :name'
        );
        $stmt->execute(['name' => $name]);

        return (int)$stmt->fetchColumn() > 0;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array($value, [1, '1', 'true', 'on', 'yes'], true);
    }
}
