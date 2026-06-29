<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/bootstrap.php';

$db = Database::connection();
$db->beginTransaction();

try {
    $adminId = ensureAdminId($db);
    ensureMinimumMembers($db);
    ensureReferenceData($db, $adminId);

    $members = $db->query(
        "SELECT m.id AS member_id, m.user_id, u.first_name, u.last_name
         FROM members m
         INNER JOIN users u ON u.id = m.user_id
         WHERE m.deleted_at IS NULL
         ORDER BY m.id"
    )->fetchAll();

    if (count($members) < 2) {
        throw new RuntimeException('Seeder needs at least two members.');
    }

    $loanBooks = [];
    $requestBooks = [];
    $reservationBooks = [];
    for ($i = 1; $i <= max(40, count($members) * 16); $i++) {
        $loanBooks[] = ensureDemoBook($db, $adminId, 'Loan Practice Volume ' . str_pad((string)$i, 2, '0', STR_PAD_LEFT), 'DEMO-LMS-LOAN-' . $i, 2);
        $requestBooks[] = ensureDemoBook($db, $adminId, 'Research Request Reader ' . str_pad((string)$i, 2, '0', STR_PAD_LEFT), 'DEMO-LMS-REQ-' . $i, 2);
        $reservationBooks[] = ensureDemoBook($db, $adminId, 'Reserved Reference Copy ' . str_pad((string)$i, 2, '0', STR_PAD_LEFT), 'DEMO-LMS-RES-' . $i, 1);
    }

    foreach ($members as $index => $member) {
        seedLoans($db, $adminId, $member, $loanBooks, $index);
        seedLoanRequests($db, $adminId, $member, $requestBooks, $index);
        seedReservations($db, $adminId, $members, $member, $reservationBooks, $index);
        seedFines($db, $adminId, $member);
        seedNotifications($db, $member);
    }

    seedAdminNotifications($db);
    seedMixedCopyStates($db, $adminId);
    normalizeActiveLoanLimit($db, $adminId);

    $db->commit();
    echo "Realistic LMS demo data seeded successfully.\n";
    echo "Members processed: " . count($members) . "\n";
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "Seeder failed: " . $e->getMessage() . "\n");
    exit(1);
}

function ensureAdminId(PDO $db): int
{
    $id = $db->query("SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = 'admin' ORDER BY u.id LIMIT 1")->fetchColumn();
    if (!$id) {
        throw new RuntimeException('Admin user not found.');
    }
    return (int)$id;
}

function ensureMinimumMembers(PDO $db): void
{
    $count = (int)$db->query("SELECT COUNT(*) FROM members WHERE deleted_at IS NULL")->fetchColumn();
    if ($count >= 3) {
        return;
    }

    $roleId = (int)$db->query("SELECT id FROM roles WHERE slug = 'member' LIMIT 1")->fetchColumn();
    $studentTypeId = (int)$db->query("SELECT id FROM member_types WHERE slug = 'student' LIMIT 1")->fetchColumn();
    $names = [
        ['demo_student2', 'liza.reyes@student.ph', 'Liza', 'Reyes'],
        ['demo_student3', 'marco.santos@student.ph', 'Marco', 'Santos'],
        ['demo_student4', 'bea.cruz@student.ph', 'Bea', 'Cruz'],
    ];

    foreach ($names as $row) {
        if ((int)$db->query("SELECT COUNT(*) FROM members WHERE deleted_at IS NULL")->fetchColumn() >= 3) {
            return;
        }
        [$username, $email, $first, $last] = $row;
        $userId = findId($db, 'users', 'username', $username);
        if (!$userId) {
            $stmt = $db->prepare(
                "INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, avatar_url, status, created_at, updated_at)
                 VALUES (:role_id, :username, :email, :password_hash, :first_name, :last_name, :avatar_url, 'active', NOW(), NOW())"
            );
            $stmt->execute([
                'role_id' => $roleId,
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash('Student_01', PASSWORD_DEFAULT),
                'first_name' => $first,
                'last_name' => $last,
                'avatar_url' => 'https://api.dicebear.com/7.x/personas/svg?seed=' . rawurlencode($username),
            ]);
            $userId = (int)$db->lastInsertId();
        }

        $memberExists = $db->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
        $memberExists->execute(['user_id' => $userId]);
        if (!$memberExists->fetchColumn()) {
            $stmt = $db->prepare(
                "INSERT INTO members (user_id, member_type_id, member_number, status, joined_at, created_at, updated_at)
                 VALUES (:user_id, :member_type_id, :member_number, 'active', DATE_SUB(CURDATE(), INTERVAL 1 YEAR), NOW(), NOW())"
            );
            $stmt->execute([
                'user_id' => $userId,
                'member_type_id' => $studentTypeId,
                'member_number' => 'M-DEMO-' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT),
            ]);
        }
    }
}

function ensureReferenceData(PDO $db, int $adminId): void
{
    ensureReference($db, 'categories', 'Computer Science', 'computer-science', $adminId);
    ensureReference($db, 'categories', 'General Education', 'general-education', $adminId);
    ensureReference($db, 'publishers', 'Quadbyte Academic Press', 'quadbyte-academic-press', $adminId);
    ensureReference($db, 'authors', 'Campus Faculty Collective', 'campus-faculty-collective', $adminId);
}

function ensureReference(PDO $db, string $table, string $name, string $slug, int $adminId): int
{
    $id = findId($db, $table, 'slug', $slug);
    if ($id) {
        return $id;
    }
    $stmt = $db->prepare("INSERT INTO {$table} (name, slug, created_by, updated_by, created_at, updated_at) VALUES (:name, :slug, :created_by, :updated_by, NOW(), NOW())");
    $stmt->execute(['name' => $name, 'slug' => $slug, 'created_by' => $adminId, 'updated_by' => $adminId]);
    return (int)$db->lastInsertId();
}

function ensureDemoBook(PDO $db, int $adminId, string $title, string $isbn, int $copies): int
{
    $bookId = findId($db, 'books', 'isbn', $isbn);
    $categoryId = ensureReference($db, 'categories', 'Computer Science', 'computer-science', $adminId);
    $publisherId = ensureReference($db, 'publishers', 'Quadbyte Academic Press', 'quadbyte-academic-press', $adminId);
    $authorId = ensureReference($db, 'authors', 'Campus Faculty Collective', 'campus-faculty-collective', $adminId);

    if (!$bookId) {
        $stmt = $db->prepare(
            "INSERT INTO books
             (category_id, publisher_id, title, isbn, publication_year, description, rack_number, cover_url,
              total_copies, late_fee_per_day, price, replacement_value, created_by, updated_by, created_at, updated_at)
             VALUES
             (:category_id, :publisher_id, :title, :isbn, 2023, :description, :rack, '', :copies, 20.00, 420.00, 420.00, :created_by, :updated_by, NOW(), NOW())"
        );
        $stmt->execute([
            'category_id' => $categoryId,
            'publisher_id' => $publisherId,
            'title' => $title,
            'isbn' => $isbn,
            'description' => 'A practical college library reference used for coursework and review sessions.',
            'rack' => 'D-' . substr($isbn, -2),
            'copies' => $copies,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $bookId = (int)$db->lastInsertId();

        $link = $db->prepare('INSERT IGNORE INTO book_authors (book_id, author_id) VALUES (:book_id, :author_id)');
        $link->execute(['book_id' => $bookId, 'author_id' => $authorId]);
    } else {
        $stmt = $db->prepare('UPDATE books SET total_copies = GREATEST(total_copies, :copies), updated_by = :updated_by, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['copies' => $copies, 'updated_by' => $adminId, 'id' => $bookId]);
    }

    ensureCopies($db, $adminId, $bookId, $copies, $isbn);
    return $bookId;
}

function ensureCopies(PDO $db, int $adminId, int $bookId, int $copies, string $prefix): void
{
    $countStmt = $db->prepare('SELECT COUNT(*) FROM book_copies WHERE book_id = :book_id AND deleted_at IS NULL');
    $countStmt->execute(['book_id' => $bookId]);
    $existing = (int)$countStmt->fetchColumn();

    for ($i = $existing + 1; $i <= $copies; $i++) {
        $copyNumber = $prefix . '-C' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
        $stmt = $db->prepare(
            "INSERT INTO book_copies (book_id, copy_number, barcode, status, acquired_at, created_by, updated_by, created_at, updated_at)
             VALUES (:book_id, :copy_number, :barcode, 'available', DATE_SUB(CURDATE(), INTERVAL 8 MONTH), :created_by, :updated_by, NOW(), NOW())"
        );
        $stmt->execute([
            'book_id' => $bookId,
            'copy_number' => $copyNumber,
            'barcode' => $copyNumber,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
    }
}

function seedLoans(PDO $db, int $adminId, array $member, array $bookIds, int $offset): void
{
    $count = countForMember($db, 'loan_transactions', (int)$member['member_id']);
    $patterns = [
        ['borrowed', -9, 5],
        ['borrowed', -20, -6],
        ['returned', -42, -28],
        ['returned', -31, -17],
        ['returned', -18, -4],
    ];

    for ($i = $count; $i < 5; $i++) {
        $bookId = $bookIds[($offset * 5 + $i) % count($bookIds)];
        $copyId = availableCopyId($db, $bookId);
        [$status, $borrowedOffset, $dueOffset] = $patterns[$i % count($patterns)];
        $borrowedAt = date('Y-m-d 09:30:00', strtotime($borrowedOffset . ' days'));
        $dueDate = date('Y-m-d', strtotime($dueOffset . ' days'));
        $returnedAt = $status === 'returned' ? date('Y-m-d 15:20:00', strtotime(($dueOffset + 1) . ' days')) : null;

        $stmt = $db->prepare(
            "INSERT INTO loan_transactions
             (member_id, book_id, copy_id, issued_by, borrowed_at, due_date, returned_at, status, return_condition, created_by, updated_by, created_at, updated_at)
             VALUES
             (:member_id, :book_id, :copy_id, :issued_by, :borrowed_at, :due_date, :returned_at, :status, :return_condition, :created_by, :updated_by, :created_at, NOW())"
        );
        $stmt->execute([
            'member_id' => (int)$member['member_id'],
            'book_id' => $bookId,
            'copy_id' => $copyId,
            'issued_by' => $adminId,
            'borrowed_at' => $borrowedAt,
            'due_date' => $dueDate,
            'returned_at' => $returnedAt,
            'status' => $status,
            'return_condition' => $status === 'returned' ? 'good' : null,
            'created_by' => $adminId,
            'updated_by' => $adminId,
            'created_at' => $borrowedAt,
        ]);

        setCopyStatus($db, $adminId, $copyId, $status === 'borrowed' ? 'borrowed' : 'available');
    }
}

function seedLoanRequests(PDO $db, int $adminId, array $member, array $bookIds, int $offset): void
{
    $count = countForMember($db, 'loan_requests', (int)$member['member_id']);
    $statuses = ['pending', 'approved', 'rejected', 'pending', 'rejected'];

    for ($i = $count; $i < 5; $i++) {
        $status = $statuses[$i % count($statuses)];
        $created = date('Y-m-d 10:00:00', strtotime(($i + 2) * -2 . ' days'));
        $reviewed = $status === 'pending' ? null : date('Y-m-d 14:00:00', strtotime(($i + 1) * -2 . ' days'));
        $stmt = $db->prepare(
            "INSERT INTO loan_requests
             (member_id, book_id, requested_due_date, status, rejection_reason, reviewed_by, reviewed_at, created_at, updated_at)
             VALUES
             (:member_id, :book_id, :due_date, :status, :reason, :reviewed_by, :reviewed_at, :created_at, NOW())"
        );
        $stmt->execute([
            'member_id' => (int)$member['member_id'],
            'book_id' => $bookIds[($offset * 5 + $i) % count($bookIds)],
            'due_date' => date('Y-m-d', strtotime(($i + 8) . ' days')),
            'status' => $status,
            'reason' => $status === 'rejected' ? 'Requested title is reserved for class use this week.' : null,
            'reviewed_by' => $status === 'pending' ? null : $adminId,
            'reviewed_at' => $reviewed,
            'created_at' => $created,
        ]);
    }
}

function seedReservations(PDO $db, int $adminId, array $members, array $member, array $bookIds, int $offset): void
{
    $count = countForMember($db, 'reservations', (int)$member['member_id']);
    $statuses = ['active', 'active', 'cancelled', 'fulfilled', 'expired'];

    for ($i = $count; $i < 5; $i++) {
        $bookId = $bookIds[($offset * 5 + $i) % count($bookIds)];
        $status = $statuses[$i % count($statuses)];
        if ($status === 'active') {
            makeBookUnavailableByOtherMember($db, $adminId, $members, (int)$member['member_id'], $bookId, $i);
        }

        $stmt = $db->prepare(
            "INSERT INTO reservations (member_id, book_id, status, queue_position, expires_at, created_at, updated_at)
             VALUES (:member_id, :book_id, :status, :queue_position, :expires_at, :created_at, NOW())"
        );
        $stmt->execute([
            'member_id' => (int)$member['member_id'],
            'book_id' => $bookId,
            'status' => $status,
            'queue_position' => $i + 1,
            'expires_at' => $status === 'active' ? date('Y-m-d 17:00:00', strtotime('+3 days')) : null,
            'created_at' => date('Y-m-d 11:15:00', strtotime(($i + 1) * -3 . ' days')),
        ]);
    }
}

function seedFines(PDO $db, int $adminId, array $member): void
{
    $count = countForMember($db, 'fines', (int)$member['member_id']);
    $loans = loanIdsForMember($db, (int)$member['member_id']);
    $statuses = ['unpaid', 'paid', 'unpaid', 'waived', 'paid'];
    $amounts = [80, 40, 120, 60, 20];

    for ($i = $count; $i < 5; $i++) {
        $loan = $loans[$i % count($loans)];
        $status = $statuses[$i % count($statuses)];
        $stmt = $db->prepare(
            "INSERT INTO fines
             (member_id, loan_transaction_id, book_id, fine_rule_id, fine_type, amount, reason, status, assessed_at, paid_at, paid_by, created_by, updated_by, created_at, updated_at)
             VALUES
             (:member_id, :loan_id, :book_id, NULL, 'overdue', :amount, :reason, :status, :assessed_at, :paid_at, :paid_by, :created_by, :updated_by, :created_at, NOW())"
        );
        $stmt->execute([
            'member_id' => (int)$member['member_id'],
            'loan_id' => (int)$loan['id'],
            'book_id' => (int)$loan['book_id'],
            'amount' => $amounts[$i % count($amounts)],
            'reason' => 'Overdue return - ' . (($i % 4) + 1) . ' day(s) late',
            'status' => $status,
            'assessed_at' => date('Y-m-d 16:30:00', strtotime(($i + 3) * -4 . ' days')),
            'paid_at' => $status === 'paid' ? date('Y-m-d 09:00:00', strtotime(($i + 2) * -3 . ' days')) : null,
            'paid_by' => $status === 'paid' ? $adminId : null,
            'created_by' => $adminId,
            'updated_by' => $adminId,
            'created_at' => date('Y-m-d 16:30:00', strtotime(($i + 3) * -4 . ' days')),
        ]);
    }
}

function seedNotifications(PDO $db, array $member): void
{
    $countStmt = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id');
    $countStmt->execute(['user_id' => (int)$member['user_id']]);
    $count = (int)$countStmt->fetchColumn();
    $messages = [
        ['Borrow request received', 'Your request has been added to the librarian queue.', 'info'],
        ['Reservation update', 'One of your reserved books is moving up in the queue.', 'info'],
        ['Due soon reminder', 'A borrowed book is due within the next three days.', 'warning'],
        ['Fine posted', 'A late return fine has been posted to your account.', 'overdue'],
        ['Return recorded', 'Thank you. Your returned book has been recorded.', 'success'],
    ];

    for ($i = $count; $i < 5; $i++) {
        [$title, $message, $type] = $messages[$i % count($messages)];
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, target_role, title, message, type, is_read, created_at)
             VALUES (:user_id, NULL, :title, :message, :type, :is_read, :created_at)"
        );
        $stmt->execute([
            'user_id' => (int)$member['user_id'],
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'is_read' => $i < 2 ? 0 : 1,
            'created_at' => date('Y-m-d H:i:s', strtotime(($i + 1) * -1 . ' days')),
        ]);
    }
}

function seedAdminNotifications(PDO $db): void
{
    $count = (int)$db->query("SELECT COUNT(*) FROM notifications WHERE target_role = 'admin'")->fetchColumn();
    for ($i = $count; $i < 5; $i++) {
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, target_role, title, message, type, is_read, created_at)
             VALUES (NULL, 'admin', :title, :message, :type, 0, :created_at)"
        );
        $stmt->execute([
            'title' => ['Pending request review', 'Fine payment posted', 'Reservation queue update', 'Overdue item alert', 'Inventory status changed'][$i % 5],
            'message' => ['A member request is waiting for approval.', 'A fine payment has been recorded.', 'A reservation queue changed today.', 'An overdue item needs follow-up.', 'A book copy changed status.'][$i % 5],
            'type' => ['info', 'success', 'info', 'overdue', 'warning'][$i % 5],
            'created_at' => date('Y-m-d H:i:s', strtotime(($i + 1) * -6 . ' hours')),
        ]);
    }
}

function seedMixedCopyStates(PDO $db, int $adminId): void
{
    $stmt = $db->query("SELECT id FROM book_copies WHERE status = 'available' AND deleted_at IS NULL ORDER BY id LIMIT 4");
    $copies = $stmt->fetchAll();
    $states = ['reserved', 'maintenance', 'available', 'available'];
    foreach ($copies as $i => $copy) {
        setCopyStatus($db, $adminId, (int)$copy['id'], $states[$i % count($states)]);
    }
}

function makeBookUnavailableByOtherMember(PDO $db, int $adminId, array $members, int $requestingMemberId, int $bookId, int $seed): void
{
    $copyId = firstCopyId($db, $bookId);
    $holder = null;
    foreach ($members as $member) {
        if ((int)$member['member_id'] !== $requestingMemberId) {
            $holder = $member;
            break;
        }
    }
    if (!$holder) {
        return;
    }
    if (activeLoanCount($db, (int)$holder['member_id']) >= 5) {
        return;
    }

    $existing = $db->prepare("SELECT id FROM loan_transactions WHERE copy_id = :copy_id AND status = 'borrowed' LIMIT 1");
    $existing->execute(['copy_id' => $copyId]);
    if (!$existing->fetchColumn()) {
        $stmt = $db->prepare(
            "INSERT INTO loan_transactions
             (member_id, book_id, copy_id, issued_by, borrowed_at, due_date, status, created_by, updated_by, created_at, updated_at)
             VALUES (:member_id, :book_id, :copy_id, :issued_by, :borrowed_at, :due_date, 'borrowed', :created_by, :updated_by, :created_at, NOW())"
        );
        $stmt->execute([
            'member_id' => (int)$holder['member_id'],
            'book_id' => $bookId,
            'copy_id' => $copyId,
            'issued_by' => $adminId,
            'borrowed_at' => date('Y-m-d 08:45:00', strtotime(($seed + 5) * -1 . ' days')),
            'due_date' => date('Y-m-d', strtotime(($seed + 9) . ' days')),
            'created_by' => $adminId,
            'updated_by' => $adminId,
            'created_at' => date('Y-m-d 08:45:00', strtotime(($seed + 5) * -1 . ' days')),
        ]);
    }
    setCopyStatus($db, $adminId, $copyId, 'borrowed');
}

function availableCopyId(PDO $db, int $bookId): int
{
    $stmt = $db->prepare("SELECT id FROM book_copies WHERE book_id = :book_id AND status = 'available' AND deleted_at IS NULL ORDER BY id LIMIT 1");
    $stmt->execute(['book_id' => $bookId]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $stmt = $db->prepare("SELECT id FROM book_copies WHERE book_id = :book_id AND deleted_at IS NULL ORDER BY id LIMIT 1");
        $stmt->execute(['book_id' => $bookId]);
        $id = $stmt->fetchColumn();
    }
    if (!$id) {
        throw new RuntimeException('No copy found for book ' . $bookId);
    }
    return (int)$id;
}

function firstCopyId(PDO $db, int $bookId): int
{
    $stmt = $db->prepare("SELECT id FROM book_copies WHERE book_id = :book_id AND deleted_at IS NULL ORDER BY id LIMIT 1");
    $stmt->execute(['book_id' => $bookId]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        throw new RuntimeException('No copy found for reservation book ' . $bookId);
    }
    return (int)$id;
}

function setCopyStatus(PDO $db, int $adminId, int $copyId, string $status): void
{
    $stmt = $db->prepare('UPDATE book_copies SET status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id');
    $stmt->execute(['status' => $status, 'updated_by' => $adminId, 'id' => $copyId]);
}

function countForMember(PDO $db, string $table, int $memberId): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$table} WHERE member_id = :member_id");
    $stmt->execute(['member_id' => $memberId]);
    return (int)$stmt->fetchColumn();
}

function activeLoanCount(PDO $db, int $memberId): int
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM loan_transactions WHERE member_id = :member_id AND status = 'borrowed'");
    $stmt->execute(['member_id' => $memberId]);
    return (int)$stmt->fetchColumn();
}

function normalizeActiveLoanLimit(PDO $db, int $adminId): void
{
    $stmt = $db->query(
        "SELECT id, member_id, copy_id
         FROM loan_transactions
         WHERE status = 'borrowed'
         ORDER BY member_id, borrowed_at DESC, id DESC"
    );
    $seen = [];
    foreach ($stmt->fetchAll() as $loan) {
        $memberId = (int)$loan['member_id'];
        $seen[$memberId] = ($seen[$memberId] ?? 0) + 1;
        if ($seen[$memberId] <= 5) {
            continue;
        }

        $db->prepare(
            "UPDATE loan_transactions
             SET status = 'returned', returned_at = NOW(), return_condition = 'good', updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id"
        )->execute(['updated_by' => $adminId, 'id' => (int)$loan['id']]);

        setCopyStatus($db, $adminId, (int)$loan['copy_id'], 'available');
    }
}

function loanIdsForMember(PDO $db, int $memberId): array
{
    $stmt = $db->prepare('SELECT id, book_id FROM loan_transactions WHERE member_id = :member_id ORDER BY id');
    $stmt->execute(['member_id' => $memberId]);
    $loans = $stmt->fetchAll();
    if (!$loans) {
        throw new RuntimeException('No loans available for member ' . $memberId);
    }
    return $loans;
}

function findId(PDO $db, string $table, string $column, string $value): ?int
{
    $stmt = $db->prepare("SELECT id FROM {$table} WHERE {$column} = :value LIMIT 1");
    $stmt->execute(['value' => $value]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}
