<?php
declare(strict_types=1);

final class AccountSchemaService extends BaseModel
{
    private static bool $ensured = false;

    public function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        $this->ensureStatusEnum('users');
        $this->ensureStatusEnum('members');

        foreach ([
            'approved_at' => 'DATETIME NULL',
            'approved_by' => 'INT NULL',
            'rejected_at' => 'DATETIME NULL',
            'rejected_by' => 'INT NULL',
            'suspended_at' => 'DATETIME NULL',
            'suspended_by' => 'INT NULL',
            'last_activity_at' => 'DATETIME NULL',
            'phone' => 'VARCHAR(40) NULL',
            'date_of_birth' => 'DATE NULL',
        ] as $column => $definition) {
            $this->ensureColumn('users', $column, $definition);
        }
    }

    private function ensureStatusEnum(string $table): void
    {
        $allowed = "ENUM('active','inactive','pending','rejected','suspended','deactivated') NOT NULL DEFAULT 'active'";
        $stmt = $this->db->prepare(
            'SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = "status"
             LIMIT 1'
        );
        $stmt->execute(['table' => $table]);
        $type = (string)$stmt->fetchColumn();
        foreach (['pending', 'rejected', 'deactivated'] as $status) {
            if (!str_contains($type, "'" . $status . "'")) {
                $this->db->exec("ALTER TABLE {$table} MODIFY status {$allowed}");
                return;
            }
        }
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => $table, 'column' => $column]);
        if ((int)$stmt->fetchColumn() === 0) {
            $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
}
