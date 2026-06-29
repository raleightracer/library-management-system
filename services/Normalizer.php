<?php
declare(strict_types=1);

final class AppNormalizer
{
    public static function user(array $row): array
    {
        $fullName = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
        $rawRoleSlug = $row['role_slug'] ?? $row['role'] ?? 'member';
        $roleSlug = $rawRoleSlug === 'staff' ? 'member' : $rawRoleSlug;
        $status = $row['member_status'] ?? $row['status'] ?? 'active';

        return [
            'id' => (string)$row['id'],
            'username' => $row['username'],
            'email' => $row['email'] ?? '',
            'firstName' => $row['first_name'] ?? '',
            'lastName' => $row['last_name'] ?? '',
            'name' => $fullName !== '' ? $fullName : $row['username'],
            'role' => $roleSlug === 'admin' ? 'admin' : 'student',
            'roleSlug' => $roleSlug,
            'memberId' => isset($row['member_id']) ? (string)$row['member_id'] : null,
            'memberType' => $row['member_type_slug'] ?? null,
            'status' => $status,
            'phone' => $row['phone'] ?? $row['phone_number'] ?? null,
            'dateOfBirth' => $row['date_of_birth'] ?? $row['dob'] ?? null,
            'dob' => $row['date_of_birth'] ?? $row['dob'] ?? null,
            'avatar' => $row['avatar_url'] ?: 'https://api.dicebear.com/7.x/personas/svg?seed=' . rawurlencode((string)$row['username']),
            'booksOut' => (int)($row['books_out'] ?? 0),
            'fineBalance' => (float)($row['fine_balance'] ?? 0),
            'createdAt' => $row['created_at'] ?? null,
            'approvedAt' => $row['approved_at'] ?? null,
            'approvedBy' => isset($row['approved_by']) ? (string)$row['approved_by'] : null,
            'rejectedAt' => $row['rejected_at'] ?? null,
            'rejectedBy' => isset($row['rejected_by']) ? (string)$row['rejected_by'] : null,
            'suspendedAt' => $row['suspended_at'] ?? null,
            'suspendedBy' => isset($row['suspended_by']) ? (string)$row['suspended_by'] : null,
            'lastLoginAt' => $row['last_login_at'] ?? null,
            'lastActivityAt' => $row['last_activity_at'] ?? null,
        ];
    }

    public static function book(array $row): array
    {
        $authors = $row['author_names'] ?? $row['author'] ?? '';
        $replacementValue = $row['replacement_value'] ?? $row['price'] ?? 500;

        return [
            'id' => (string)$row['id'],
            'title' => $row['title'] ?? '',
            'author' => $authors !== '' ? $authors : 'Unknown Author',
            'authors' => $authors,
            'isbn' => $row['isbn'] ?? '',
            'subject' => $row['category_name'] ?? $row['subject'] ?? '',
            'categoryId' => isset($row['category_id']) ? (string)$row['category_id'] : null,
            'publisher' => $row['publisher_name'] ?? $row['publisher'] ?? '',
            'publisherId' => isset($row['publisher_id']) ? (string)$row['publisher_id'] : null,
            'year' => isset($row['publication_year']) ? (int)$row['publication_year'] : (int)($row['year'] ?? 0),
            'copies' => (int)($row['total_copies'] ?? $row['copies'] ?? 0),
            'availableCopies' => (int)($row['available_copies'] ?? 0),
            'borrowedCopies' => (int)($row['borrowed_copies'] ?? 0),
            'lostCopies' => (int)($row['lost_copies'] ?? 0),
            'lateFeePerDay' => (float)($row['late_fee_per_day'] ?? 20),
            'baseFine' => (float)$replacementValue,
            'rack' => $row['rack_number'] ?? '',
            'cover' => $row['cover_url'] ?? '',
            'desc' => $row['description'] ?? '',
        ];
    }

    public static function loan(array $row): array
    {
        $status = $row['status'] ?? 'borrowed';
        $returned = in_array($status, ['returned'], true);
        $lost = in_array($status, ['lost', 'damaged'], true);

        return [
            'id' => (string)$row['id'],
            'bookId' => (string)$row['book_id'],
            'copyId' => isset($row['copy_id']) ? (string)$row['copy_id'] : null,
            'userId' => (string)$row['user_id'],
            'memberId' => isset($row['member_id']) ? (string)$row['member_id'] : null,
            'requestId' => isset($row['loan_request_id']) ? (string)$row['loan_request_id'] : null,
            'created' => self::millis($row['borrowed_at'] ?? $row['created_at'] ?? null),
            'dueDate' => self::dateOnly($row['due_date'] ?? null),
            'returned' => $returned,
            'lost' => $lost,
            'lostOrDamaged' => $lost,
            'returnedDate' => self::millis($row['returned_at'] ?? null),
            'renewed' => (int)($row['renew_count'] ?? 0),
            'status' => $status,
        ];
    }

    public static function request(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'bookId' => (string)$row['book_id'],
            'userId' => (string)$row['user_id'],
            'memberId' => isset($row['member_id']) ? (string)$row['member_id'] : null,
            'created' => self::millis($row['created_at'] ?? null),
            'dueDate' => self::dateOnly($row['requested_due_date'] ?? null),
            'status' => $row['status'] ?? 'pending',
            'handledDate' => self::millis($row['reviewed_at'] ?? null),
        ];
    }

    public static function reservation(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'bookId' => (string)$row['book_id'],
            'userId' => (string)$row['user_id'],
            'memberId' => isset($row['member_id']) ? (string)$row['member_id'] : null,
            'date' => self::millis($row['created_at'] ?? null),
            'status' => $row['status'] ?? 'active',
            'position' => (int)($row['queue_position'] ?? 1),
            'readyAt' => self::millis($row['ready_at'] ?? null),
            'fulfilledAt' => self::millis($row['fulfilled_at'] ?? null),
            'cancelledAt' => self::millis($row['cancelled_at'] ?? null),
            'expiredAt' => self::millis($row['expired_at'] ?? null),
            'expiresAt' => self::millis($row['expires_at'] ?? null),
        ];
    }

    public static function fine(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'userId' => (string)$row['user_id'],
            'memberId' => isset($row['member_id']) ? (string)$row['member_id'] : null,
            'bookId' => isset($row['book_id']) ? (string)$row['book_id'] : null,
            'txId' => isset($row['loan_transaction_id']) ? (string)$row['loan_transaction_id'] : null,
            'reason' => $row['reason'] ?? $row['fine_type'] ?? 'Fine',
            'amount' => (float)($row['amount'] ?? 0),
            'date' => self::millis($row['assessed_at'] ?? $row['created_at'] ?? null),
            'status' => $row['status'] ?? 'unpaid',
            'paid' => ($row['status'] ?? '') === 'paid',
            'waived' => ($row['status'] ?? '') === 'waived',
            'paidDate' => self::millis($row['paid_at'] ?? null),
            'isReplacementFee' => in_array(($row['fine_type'] ?? ''), ['lost', 'damaged'], true),
        ];
    }

    public static function notification(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'message' => $row['message'] ?? '',
            'title' => $row['title'] ?? '',
            'type' => $row['type'] ?? 'info',
            'date' => self::millis($row['created_at'] ?? null),
            'read' => (bool)($row['is_read'] ?? false),
            'deletedAt' => $row['deleted_at'] ?? null,
            'targetRole' => $row['target_role'] ?? null,
            'isShared' => empty($row['user_id']),
            'isStaffOnly' => str_contains((string)($row['action_type'] ?? ''), 'staff_only')
                || ($row['related_entity_type'] ?? null) === 'staff',
            'recipientId' => !empty($row['recipient_id'])
                ? (string)$row['recipient_id']
                : (!empty($row['user_id']) ? (string)$row['user_id'] : ($row['target_role'] ?? 'both')),
            'txId' => isset($row['loan_transaction_id']) ? (string)$row['loan_transaction_id'] : null,
            'related_entity_type' => $row['related_entity_type'] ?? null,
            'related_entity_id' => isset($row['related_entity_id']) ? (string)$row['related_entity_id'] : null,
            'action_type' => $row['action_type'] ?? null,
            'referenceId' => isset($row['related_entity_id']) && $row['related_entity_id'] !== null
                ? (string)$row['related_entity_id']
                : (isset($row['loan_transaction_id']) ? (string)$row['loan_transaction_id'] : null),
            'referenceType' => $row['related_entity_type']
                ?? (isset($row['loan_transaction_id']) && $row['loan_transaction_id'] !== null ? 'loan' : null),
            'actionType' => $row['action_type'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
        ];
    }

    public static function millis(?string $value): int
    {
        if (!$value) {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp ? $timestamp * 1000 : 0;
    }

    private static function dateOnly(?string $value): string
    {
        if (!$value) {
            return '';
        }

        return substr($value, 0, 10);
    }
}
