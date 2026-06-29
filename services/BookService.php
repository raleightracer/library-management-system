<?php
declare(strict_types=1);

final class BookService extends BaseModel
{
    public function list(array $filters = []): array
    {
        $params = [];
        $where = ['b.deleted_at IS NULL'];

        if (!empty($filters['search'])) {
            $where[] = '(b.title LIKE :search_title OR b.isbn LIKE :search_isbn OR c.name LIKE :search_category OR p.name LIKE :search_publisher OR a.name LIKE :search_author)';
            $search = '%' . trim((string)$filters['search']) . '%';
            $params['search_title'] = $search;
            $params['search_isbn'] = $search;
            $params['search_category'] = $search;
            $params['search_publisher'] = $search;
            $params['search_author'] = $search;
        }

        if (!empty($filters['category_id'])) {
            $where[] = 'b.category_id = :category_id';
            $params['category_id'] = (int)$filters['category_id'];
        }

        if (!empty($filters['year'])) {
            $where[] = 'b.publication_year = :year';
            $params['year'] = (int)$filters['year'];
        }

        $having = '';
        if (($filters['availability'] ?? '') === 'available') {
            $having = ' HAVING available_copies > 0';
        } elseif (($filters['availability'] ?? '') === 'borrowed') {
            $having = ' HAVING borrowed_copies > 0';
        } elseif (($filters['availability'] ?? '') === 'lost') {
            $having = ' HAVING lost_copies > 0';
        }

        $sql = $this->bookListSql('WHERE ' . implode(' AND ', $where), $having);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([AppNormalizer::class, 'book'], $stmt->fetchAll());
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare($this->bookListSql('WHERE b.id = :id AND b.deleted_at IS NULL'));
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? AppNormalizer::book($row) : null;
    }

    public function create(array $input, int $userId): array
    {
        Request::requireFields($input, ['title', 'author']);
        $copyCount = max(1, (int)($input['copies'] ?? 1));
        $refs = new ReferenceService($this->db);
        $categoryId = $refs->ensure('categories', $input['subject'] ?? $input['category'] ?? null, $userId);
        $publisherId = $refs->ensure('publishers', $input['publisher'] ?? null, $userId);
        $authorIds = $this->ensureAuthors((string)$input['author'], $userId);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO books
                 (category_id, publisher_id, title, isbn, publication_year, description, rack_number, cover_url,
                  total_copies, late_fee_per_day, price, replacement_value, created_by, updated_by, created_at, updated_at)
                 VALUES
                 (:category_id, :publisher_id, :title, :isbn, :publication_year, :description, :rack_number, :cover_url,
                  :total_copies, :late_fee_per_day, :price, :replacement_value, :created_by, :updated_by, NOW(), NOW())'
            );
            $stmt->execute([
                'category_id' => $categoryId,
                'publisher_id' => $publisherId,
                'title' => trim((string)$input['title']),
                'isbn' => trim((string)($input['isbn'] ?? '')),
                'publication_year' => $this->nullableInt($input['year'] ?? $input['publication_year'] ?? null),
                'description' => trim((string)($input['desc'] ?? $input['description'] ?? '')),
                'rack_number' => trim((string)($input['rack'] ?? $input['rack_number'] ?? '')),
                'cover_url' => trim((string)($input['cover'] ?? $input['cover_url'] ?? '')),
                'total_copies' => $copyCount,
                'late_fee_per_day' => (float)($input['lateFeePerDay'] ?? $input['late_fee_per_day'] ?? 20),
                'price' => (float)($input['price'] ?? $input['baseFine'] ?? $input['replacement_value'] ?? 500),
                'replacement_value' => (float)($input['baseFine'] ?? $input['replacement_value'] ?? $input['price'] ?? 500),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $bookId = (int)$this->db->lastInsertId();

            $this->syncAuthors($bookId, $authorIds);
            $this->addCopies($bookId, $copyCount, $userId);

            (new NotificationService($this->db))->create(null, 'admin', 'New book added', 'New book added: "' . trim((string)$input['title']) . '"', 'info', null, 'book', $bookId, 'created');
            (new AuditService($this->db))->log($userId, 'create', 'books', $bookId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->find($bookId) ?? [];
    }

    public function update(int $id, array $input, int $userId): array
    {
        Request::requireFields($input, ['title', 'author']);
        $copyCount = max(1, (int)($input['copies'] ?? 1));
        $refs = new ReferenceService($this->db);
        $categoryId = $refs->ensure('categories', $input['subject'] ?? $input['category'] ?? null, $userId);
        $publisherId = $refs->ensure('publishers', $input['publisher'] ?? null, $userId);
        $authorIds = $this->ensureAuthors((string)$input['author'], $userId);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id FROM books WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $id]);
            if (!$stmt->fetch()) {
                Response::error('Book not found.', 404);
            }

            $this->setCopyCount($id, $copyCount, $userId);

            $stmt = $this->db->prepare(
                'UPDATE books SET
                    category_id = :category_id,
                    publisher_id = :publisher_id,
                    title = :title,
                    isbn = :isbn,
                    publication_year = :publication_year,
                    description = :description,
                    rack_number = :rack_number,
                    cover_url = :cover_url,
                    total_copies = :total_copies,
                    late_fee_per_day = :late_fee_per_day,
                    price = :price,
                    replacement_value = :replacement_value,
                    updated_by = :updated_by,
                    updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'category_id' => $categoryId,
                'publisher_id' => $publisherId,
                'title' => trim((string)$input['title']),
                'isbn' => trim((string)($input['isbn'] ?? '')),
                'publication_year' => $this->nullableInt($input['year'] ?? $input['publication_year'] ?? null),
                'description' => trim((string)($input['desc'] ?? $input['description'] ?? '')),
                'rack_number' => trim((string)($input['rack'] ?? $input['rack_number'] ?? '')),
                'cover_url' => trim((string)($input['cover'] ?? $input['cover_url'] ?? '')),
                'total_copies' => $copyCount,
                'late_fee_per_day' => (float)($input['lateFeePerDay'] ?? $input['late_fee_per_day'] ?? 20),
                'price' => (float)($input['price'] ?? $input['baseFine'] ?? $input['replacement_value'] ?? 500),
                'replacement_value' => (float)($input['baseFine'] ?? $input['replacement_value'] ?? $input['price'] ?? 500),
                'updated_by' => $userId,
                'id' => $id,
            ]);

            $this->syncAuthors($id, $authorIds);
            (new AuditService($this->db))->log($userId, 'update', 'books', $id);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->find($id) ?? [];
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $book = $this->db->prepare('SELECT id FROM books WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            $book->execute(['id' => $id]);
            if (!$book->fetch()) {
                $this->db->rollBack();
                Response::error('Book not found.', 404);
            }

            $copies = $this->db->prepare(
                'SELECT id FROM book_copies WHERE book_id = :book_id AND deleted_at IS NULL FOR UPDATE'
            );
            $copies->execute(['book_id' => $id]);

            $active = $this->db->prepare(
                "SELECT id FROM loan_transactions WHERE book_id = :book_id AND status = 'borrowed' LIMIT 1 FOR UPDATE"
            );
            $active->execute(['book_id' => $id]);
            if ($active->fetch()) {
                $this->db->rollBack();
                Response::error('Cannot delete a book with active loans.', 409);
            }

            $stmt = $this->db->prepare('UPDATE books SET deleted_at = NOW(), deleted_by = :deleted_by WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute(['deleted_by' => $userId, 'id' => $id]);
            if ($stmt->rowCount() !== 1) {
                $this->db->rollBack();
                Response::error('Book could not be deleted.', 409);
            }

            $copyStmt = $this->db->prepare(
                "UPDATE book_copies SET deleted_at = NOW(), deleted_by = :deleted_by
                 WHERE book_id = :book_id AND status = 'available' AND deleted_at IS NULL"
            );
            $copyStmt->execute(['deleted_by' => $userId, 'book_id' => $id]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function availableCopy(int $bookId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM book_copies
             WHERE book_id = :book_id AND status = 'available' AND deleted_at IS NULL
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute(['book_id' => $bookId]);
        $copy = $stmt->fetch();

        return $copy ?: null;
    }

    private function bookListSql(string $where = '', string $having = ''): string
    {
        return "
            SELECT
                b.*,
                c.name AS category_name,
                p.name AS publisher_name,
                GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR ', ') AS author_names,
                COUNT(DISTINCT CASE WHEN bc.deleted_at IS NULL AND bc.status = 'available' THEN bc.id END) AS available_copies,
                COUNT(DISTINCT CASE WHEN bc.deleted_at IS NULL AND bc.status = 'borrowed' THEN bc.id END) AS borrowed_copies,
                COUNT(DISTINCT CASE WHEN bc.deleted_at IS NULL AND bc.status IN ('lost', 'damaged') THEN bc.id END) AS lost_copies
            FROM books b
            LEFT JOIN categories c ON c.id = b.category_id
            LEFT JOIN publishers p ON p.id = b.publisher_id
            LEFT JOIN book_authors ba ON ba.book_id = b.id
            LEFT JOIN authors a ON a.id = ba.author_id AND a.deleted_at IS NULL
            LEFT JOIN book_copies bc ON bc.book_id = b.id
            {$where}
            GROUP BY b.id
            {$having}
            ORDER BY b.created_at DESC, b.id DESC
        ";
    }

    private function ensureAuthors(string $authorText, int $userId): array
    {
        $names = array_filter(array_map('trim', explode(',', $authorText)));
        if ($names === []) {
            $names = ['Unknown Author'];
        }

        $refs = new ReferenceService($this->db);
        $ids = [];
        foreach ($names as $name) {
            $id = $refs->ensure('authors', $name, $userId);
            if ($id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function syncAuthors(int $bookId, array $authorIds): void
    {
        $this->db->prepare('DELETE FROM book_authors WHERE book_id = :book_id')->execute(['book_id' => $bookId]);
        $stmt = $this->db->prepare('INSERT INTO book_authors (book_id, author_id) VALUES (:book_id, :author_id)');
        foreach ($authorIds as $authorId) {
            $stmt->execute(['book_id' => $bookId, 'author_id' => $authorId]);
        }
    }

    private function setCopyCount(int $bookId, int $targetCount, int $userId): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM book_copies WHERE book_id = :book_id AND deleted_at IS NULL');
        $stmt->execute(['book_id' => $bookId]);
        $current = (int)$stmt->fetchColumn();

        if ($targetCount > $current) {
            $this->addCopies($bookId, $targetCount - $current, $userId, $current + 1);
            return;
        }

        if ($targetCount === $current) {
            return;
        }

        $removeCount = $current - $targetCount;
        $available = $this->db->prepare(
            "SELECT id FROM book_copies
             WHERE book_id = :book_id AND status = 'available' AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT {$removeCount}"
        );
        $available->execute(['book_id' => $bookId]);
        $ids = array_column($available->fetchAll(), 'id');

        if (count($ids) < $removeCount) {
            Response::error('Copies cannot be lower than checked-out or unavailable copies.', 422);
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $delete = $this->db->prepare("UPDATE book_copies SET deleted_at = NOW(), deleted_by = ? WHERE id IN ({$in})");
        $delete->execute(array_merge([$userId], $ids));
    }

    private function addCopies(int $bookId, int $count, int $userId, int $start = 1): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO book_copies (book_id, copy_number, barcode, status, created_by, updated_by, created_at, updated_at)
             VALUES (:book_id, :copy_number, :barcode, "available", :created_by, :updated_by, NOW(), NOW())'
        );

        for ($i = 0; $i < $count; $i++) {
            $number = $start + $i;
            $copyNumber = sprintf('B%05d-C%03d', $bookId, $number);
            $stmt->execute([
                'book_id' => $bookId,
                'copy_number' => $copyNumber,
                'barcode' => $copyNumber,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
    }
}
