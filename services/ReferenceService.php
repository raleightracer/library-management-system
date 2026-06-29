<?php
declare(strict_types=1);

final class ReferenceService extends BaseModel
{
    public function list(string $table): array
    {
        $table = $this->safeTable($table);
        $stmt = $this->db->query("SELECT id, name, slug, created_at, updated_at FROM {$table} WHERE deleted_at IS NULL ORDER BY name");
        return $stmt->fetchAll();
    }

    public function create(string $table, string $name, ?int $userId = null): array
    {
        $table = $this->safeTable($table);
        $name = trim($name);
        if ($name === '') {
            Response::error('Name is required.', 422);
        }

        $slug = $this->slug($name);
        $stmt = $this->db->prepare(
            "INSERT INTO {$table} (name, slug, created_by, updated_by, created_at, updated_at)
             VALUES (:name, :slug, :created_by, :updated_by, NOW(), NOW())
             ON DUPLICATE KEY UPDATE name = VALUES(name), deleted_at = NULL, updated_at = NOW()"
        );
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return $this->findByName($table, $name);
    }

    public function update(string $table, int $id, string $name, ?int $userId = null): array
    {
        $table = $this->safeTable($table);
        $stmt = $this->db->prepare(
            "UPDATE {$table} SET name = :name, slug = :slug, updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute([
            'name' => trim($name),
            'slug' => $this->slug($name),
            'updated_by' => $userId,
            'id' => $id,
        ]);

        $row = $this->findById($table, $id);
        if (!$row) {
            Response::error('Reference not found.', 404);
        }

        return $row;
    }

    public function delete(string $table, int $id, ?int $userId = null): void
    {
        $table = $this->safeTable($table);
        $this->assertNotReferenced($table, $id);
        $stmt = $this->db->prepare("UPDATE {$table} SET deleted_at = NOW(), deleted_by = :deleted_by WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['deleted_by' => $userId, 'id' => $id]);
        if ($stmt->rowCount() !== 1) {
            Response::error('Reference not found.', 404);
        }
    }

    public function ensure(string $table, ?string $name, ?int $userId = null): ?int
    {
        $name = trim((string)$name);
        if ($name === '') {
            return null;
        }

        $table = $this->safeTable($table);
        $found = $this->findByName($table, $name);
        if ($found) {
            return (int)$found['id'];
        }

        $created = $this->create($table, $name, $userId);
        return (int)$created['id'];
    }

    private function findByName(string $table, string $name): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE LOWER(name) = LOWER(:name) AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['name' => trim($name)]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findById(string $table, int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function safeTable(string $table): string
    {
        $allowed = ['authors', 'categories', 'publishers'];
        if (!in_array($table, $allowed, true)) {
            Response::error('Invalid reference type.', 400);
        }

        return $table;
    }

    private function assertNotReferenced(string $table, int $id): void
    {
        $queries = [
            'authors' => 'SELECT COUNT(*) FROM book_authors ba INNER JOIN books b ON b.id = ba.book_id WHERE ba.author_id = :id AND b.deleted_at IS NULL',
            'publishers' => 'SELECT COUNT(*) FROM books WHERE publisher_id = :id AND deleted_at IS NULL',
            'categories' => 'SELECT COUNT(*) FROM books WHERE category_id = :id AND deleted_at IS NULL',
        ];

        $stmt = $this->db->prepare($queries[$table]);
        $stmt->execute(['id' => $id]);
        if ((int)$stmt->fetchColumn() > 0) {
            Response::error('This reference is used by existing books and cannot be deleted.', 409);
        }
    }

    private function slug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        return $slug !== '' ? $slug : bin2hex(random_bytes(4));
    }
}
