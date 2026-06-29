<?php
declare(strict_types=1);

final class AuthService extends BaseModel
{
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db);
        (new AccountSchemaService($this->db))->ensure();
    }

    public function currentUser(): ?array
    {
        $session = Auth::user();
        if (!$session) {
            return null;
        }

        $row = $this->findUserById((int)$session['id']);
        return $row ? AppNormalizer::user($row) : null;
    }

    public function login(string $username, string $password, bool $remember = false): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.slug AS role_slug, m.id AS member_id, m.status AS member_status, mt.slug AS member_type_slug
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN members m ON m.user_id = u.id AND m.deleted_at IS NULL
             LEFT JOIN member_types mt ON mt.id = m.member_type_id
             WHERE u.username = :username AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            Response::error('Invalid username or password.', 401);
        }

        $userStatus = (string)($user['status'] ?? '');
        $memberStatus = (string)($user['member_status'] ?? '');
        if (!in_array($userStatus, ['active', 'approved'], true)) {
            Response::error($this->loginStatusMessage($userStatus), 403);
        }
        if (($user['role_slug'] ?? '') !== 'admin' && !in_array($memberStatus, ['active', 'approved'], true)) {
            Response::error($this->loginStatusMessage($memberStatus), 403);
        }

        Auth::login($user);
        if ($remember) {
            Auth::createRememberToken((int)$user['id']);
        }

        $this->db->prepare('UPDATE users SET last_login_at = NOW(), last_activity_at = NOW() WHERE id = :id')->execute(['id' => (int)$user['id']]);
        (new AuditService($this->db))->log((int)$user['id'], 'login', 'users', (int)$user['id']);

        return AppNormalizer::user($user);
    }

    public function signup(array $input): array
    {
        Request::requireFields($input, ['first_name', 'last_name', 'email', 'username', 'password']);
        $this->assertStrongPassword((string)$input['password']);

        try {
            $this->assertUniqueLogin((string)$input['username'], (string)$input['email']);
            $roleId = $this->roleId('member');
            $memberTypeId = $this->memberTypeId($input['member_type'] ?? 'student');
            $avatar = $input['avatar'] ?? ('https://api.dicebear.com/7.x/personas/svg?seed=' . rawurlencode((string)$input['username']));

            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, avatar_url, status, created_at, updated_at)
                 VALUES (:role_id, :username, :email, :password_hash, :first_name, :last_name, :avatar_url, "pending", NOW(), NOW())'
            );
            $stmt->execute([
                'role_id' => $roleId,
                'username' => trim((string)$input['username']),
                'email' => trim((string)$input['email']),
                'password_hash' => password_hash((string)$input['password'], PASSWORD_DEFAULT),
                'first_name' => trim((string)$input['first_name']),
                'last_name' => trim((string)$input['last_name']),
                'avatar_url' => $avatar,
            ]);
            $userId = (int)$this->db->lastInsertId();

            $memberNo = 'M-' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);
            $memberStmt = $this->db->prepare(
                'INSERT INTO members (user_id, member_type_id, member_number, status, joined_at, created_at, updated_at)
                 VALUES (:user_id, :member_type_id, :member_number, "pending", CURDATE(), NOW(), NOW())'
            );
            $memberStmt->execute([
                'user_id' => $userId,
                'member_type_id' => $memberTypeId,
                'member_number' => $memberNo,
            ]);

            (new AuditService($this->db))->log($userId, 'signup', 'users', $userId);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->getNormalizedUser($userId);
    }

    public function updateProfile(array $currentUser, array $input): array
    {
        $fields = [];
        $params = ['id' => (int)$currentUser['id']];

        foreach (['first_name', 'last_name', 'email', 'avatar_url', 'phone', 'date_of_birth'] as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = $field . ' = :' . $field;
                $value = trim((string)$input[$field]);
                $params[$field] = $field === 'date_of_birth' && $value === '' ? null : $value;
            }
        }

        if ($fields === []) {
            return $this->getNormalizedUser((int)$currentUser['id']);
        }

        if (isset($params['email'])) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :email AND id <> :id AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['email' => $params['email'], 'id' => (int)$currentUser['id']]);
            if ($stmt->fetch()) {
                Response::error('Email is already in use.', 409);
            }
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW(), updated_by = :updated_by WHERE id = :id';
        $params['updated_by'] = (int)$currentUser['id'];
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $this->findUserById((int)$currentUser['id']);
        if ($row) {
            Auth::login($row);
        }

        return AppNormalizer::user($row);
    }

    public function updateSecurity(array $currentUser, array $input): array
    {
        Request::requireFields($input, ['username']);
        $username = trim((string)$input['username']);

        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = :username AND id <> :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['username' => $username, 'id' => (int)$currentUser['id']]);
        if ($stmt->fetch()) {
            Response::error('Username is already taken.', 409);
        }

        $params = [
            'username' => $username,
            'id' => (int)$currentUser['id'],
            'updated_by' => (int)$currentUser['id'],
        ];
        $passwordSql = '';

        if (array_key_exists('password', $input) && (string)$input['password'] !== '') {
            $this->assertStrongPassword((string)$input['password']);
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash((string)$input['password'], PASSWORD_DEFAULT);
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET username = :username' . $passwordSql . ', updated_at = NOW(), updated_by = :updated_by WHERE id = :id'
        );
        $stmt->execute($params);

        $row = $this->findUserById((int)$currentUser['id']);
        if ($row) {
            Auth::login($row);
        }

        return AppNormalizer::user($row);
    }

    public function listMembers(?string $search = null): array
    {
        $where = 'u.deleted_at IS NULL';
        $params = [];
        if ($search) {
            $where .= ' AND (u.first_name LIKE :search OR u.last_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT u.*, r.slug AS role_slug, m.id AS member_id, m.status AS member_status,
                    mt.slug AS member_type_slug,
                    COALESCE(loan_counts.books_out, 0) AS books_out,
                    COALESCE(fine_totals.fine_balance, 0) AS fine_balance
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN members m ON m.user_id = u.id AND m.deleted_at IS NULL
             LEFT JOIN member_types mt ON mt.id = m.member_type_id
             LEFT JOIN (
                SELECT member_id, COUNT(*) AS books_out
                FROM loan_transactions
                WHERE status = 'borrowed'
                GROUP BY member_id
             ) loan_counts ON loan_counts.member_id = m.id
             LEFT JOIN (
                SELECT member_id, SUM(amount) AS fine_balance
                FROM fines
                WHERE status = 'unpaid'
                GROUP BY member_id
             ) fine_totals ON fine_totals.member_id = m.id
             WHERE {$where}
             ORDER BY u.created_at DESC"
        );
        $stmt->execute($params);

        return array_map([AppNormalizer::class, 'user'], $stmt->fetchAll());
    }

    public function createPrivilegedAccount(array $input, int $adminId): array
    {
        Request::requireFields($input, ['first_name', 'last_name', 'email', 'username', 'password', 'role_slug']);
        $this->assertStrongPassword((string)$input['password']);
        $roleSlug = $this->assignableRoleSlug((string)$input['role_slug']);
        if ($roleSlug !== 'admin') {
            Response::error('Only administrator accounts can be created here. Staff should be created as member accounts with staff member type.', 422);
        }

        $this->assertUniqueLogin((string)$input['username'], (string)$input['email']);
        $roleId = $this->roleId($roleSlug);
        $avatar = $input['avatar'] ?? ('https://api.dicebear.com/7.x/personas/svg?seed=' . rawurlencode((string)$input['username']));

        $stmt = $this->db->prepare(
            'INSERT INTO users
             (role_id, username, email, password_hash, first_name, last_name, phone, avatar_url, status, approved_at, approved_by, created_by, updated_by, created_at, updated_at)
             VALUES
             (:role_id, :username, :email, :password_hash, :first_name, :last_name, :phone, :avatar_url, "active", NOW(), :approved_by, :created_by, :updated_by, NOW(), NOW())'
        );
        $stmt->execute([
            'role_id' => $roleId,
            'username' => trim((string)$input['username']),
            'email' => trim((string)$input['email']),
            'password_hash' => password_hash((string)$input['password'], PASSWORD_DEFAULT),
            'first_name' => trim((string)$input['first_name']),
            'last_name' => trim((string)$input['last_name']),
            'phone' => trim((string)($input['phone'] ?? '')) ?: null,
            'avatar_url' => $avatar,
            'approved_by' => $adminId,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
        $userId = (int)$this->db->lastInsertId();

        (new AuditService($this->db))->log($adminId, 'create_' . $roleSlug, 'users', $userId);

        return ['user' => $this->getNormalizedUser($userId)];
    }

    public function updateUserRole(int $userId, int $adminId, string $roleSlug): array
    {
        $roleSlug = $this->assignableRoleSlug($roleSlug);
        $user = $this->findUserById($userId);
        if (!$user) {
            Response::error('User not found.', 404);
        }
        $oldRole = (string)($user['role_slug'] ?? '');
        if ($userId === $adminId && $oldRole === 'admin' && $roleSlug !== 'admin') {
            Response::error('You cannot remove your own administrator access.', 409);
        }
        if ($oldRole === 'admin' && $roleSlug !== 'admin') {
            $this->assertAnotherActiveAdminExists($userId);
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'UPDATE users SET role_id = :role_id, updated_by = :admin_id, updated_at = NOW() WHERE id = :user_id'
            )->execute([
                'role_id' => $this->roleId($roleSlug),
                'admin_id' => $adminId,
                'user_id' => $userId,
            ]);

            if ($roleSlug === 'member') {
                $this->ensureMemberRecord($userId, $adminId);
            }

            (new AuditService($this->db))->log($adminId, 'update_role', 'users', $userId, [
                'from' => $oldRole,
                'to' => $roleSlug,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['user' => $this->getNormalizedUser($userId)];
    }

    public function updateManagedAccount(int $userId, int $adminId, array $input): array
    {
        $user = $this->findUserById($userId);
        if (!$user) {
            Response::error('User not found.', 404);
        }

        $fields = [];
        $params = ['user_id' => $userId, 'admin_id' => $adminId];
        foreach (['first_name', 'last_name', 'email', 'username', 'phone'] as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = $field . ' = :' . $field;
                $params[$field] = trim((string)$input[$field]);
            }
        }
        if (array_key_exists('password', $input) && (string)$input['password'] !== '') {
            $this->assertStrongPassword((string)$input['password']);
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash((string)$input['password'], PASSWORD_DEFAULT);
        }
        if ($fields === []) {
            return ['user' => $this->getNormalizedUser($userId)];
        }
        if (isset($params['email']) || isset($params['username'])) {
            $this->assertUniqueLoginForUser(
                (string)($params['username'] ?? $user['username']),
                (string)($params['email'] ?? $user['email']),
                $userId
            );
        }

        $this->db->prepare(
            'UPDATE users SET ' . implode(', ', $fields) . ', updated_by = :admin_id, updated_at = NOW() WHERE id = :user_id'
        )->execute($params);
        (new AuditService($this->db))->log($adminId, 'update_account', 'users', $userId);

        return ['user' => $this->getNormalizedUser($userId)];
    }

    public function deactivateMember(int $userId, int $adminId, ?string $reason = null): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id
             FROM members m
             INNER JOIN users u ON u.id = m.user_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :user_id AND r.slug = 'member' AND u.deleted_at IS NULL AND m.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId]);
        $member = $stmt->fetch();
        if (!$member) {
            Response::error('Member not found.', 404);
        }

        $update = $this->db->prepare(
            "UPDATE members
             SET status = 'inactive', updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id"
        );
        $update->execute(['updated_by' => $adminId, 'id' => (int)$member['id']]);

        (new AuditService($this->db))->log($adminId, 'deactivate', 'members', (int)$member['id'], [
            'reason' => $reason ?: 'not specified',
            'user_id' => $userId,
        ]);

        return $this->getNormalizedUser($userId);
    }

    public function approveUser(int $userId, int $adminId): array
    {
        $user = $this->statusActionTarget($userId, $adminId);
        $this->setAccountStatus($userId, $adminId, 'active', [
            'approved_at' => 'NOW()',
            'approved_by' => ':admin_id',
            'rejected_at' => 'NULL',
            'rejected_by' => 'NULL',
            'suspended_at' => 'NULL',
            'suspended_by' => 'NULL',
        ]);
        (new AuditService($this->db))->log($adminId, 'approve_user', 'users', $userId);

        $normalized = $this->getNormalizedUser($userId);
        $mail = (new MailService())->sendAccountDecision(
            (string)$user['email'],
            trim((string)$user['first_name'] . ' ' . (string)$user['last_name']) ?: (string)$user['username'],
            'Approved',
            'Your SchoDex account has been approved and is now active. You can sign in using your registered username and password.'
        );

        return ['user' => $normalized, 'mail' => $mail];
    }

    public function rejectUser(int $userId, int $adminId, ?string $reason = null): array
    {
        $user = $this->statusActionTarget($userId, $adminId);
        if (($user['status'] ?? '') === 'active') {
            Response::error('Active users must be suspended or deactivated instead of rejected.', 409);
        }
        $this->setAccountStatus($userId, $adminId, 'rejected', [
            'rejected_at' => 'NOW()',
            'rejected_by' => ':admin_id',
        ]);
        (new AuditService($this->db))->log($adminId, 'reject_user', 'users', $userId, ['reason' => $reason ?: 'not specified']);

        $message = 'Your SchoDex account registration was not approved.';
        if ($reason) {
            $message .= "\n\nReason: " . trim($reason);
        }
        $normalized = $this->getNormalizedUser($userId);
        $mail = (new MailService())->sendAccountDecision(
            (string)$user['email'],
            trim((string)$user['first_name'] . ' ' . (string)$user['last_name']) ?: (string)$user['username'],
            'Rejected',
            $message
        );

        return ['user' => $normalized, 'mail' => $mail];
    }

    public function suspendUser(int $userId, int $adminId, ?string $reason = null): array
    {
        $this->statusActionTarget($userId, $adminId);
        $this->setAccountStatus($userId, $adminId, 'suspended', [
            'suspended_at' => 'NOW()',
            'suspended_by' => ':admin_id',
        ]);
        $this->db->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id')->execute(['user_id' => $userId]);
        (new AuditService($this->db))->log($adminId, 'suspend_user', 'users', $userId, ['reason' => $reason ?: 'not specified']);

        return ['user' => $this->getNormalizedUser($userId)];
    }

    public function reactivateUser(int $userId, int $adminId): array
    {
        $user = $this->statusActionTarget($userId, $adminId);
        if (($user['status'] ?? '') === 'pending') {
            Response::error('Pending users must be approved instead of reactivated.', 409);
        }
        $this->setAccountStatus($userId, $adminId, 'active', [
            'suspended_at' => 'NULL',
            'suspended_by' => 'NULL',
        ]);
        (new AuditService($this->db))->log($adminId, 'reactivate_user', 'users', $userId);

        return ['user' => $this->getNormalizedUser($userId)];
    }

    public function findUserById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.slug AS role_slug, m.id AS member_id, m.status AS member_status, mt.slug AS member_type_slug,
                    (SELECT COUNT(*) FROM loan_transactions lt WHERE lt.member_id = m.id AND lt.status = "borrowed") AS books_out,
                    (SELECT COALESCE(SUM(f.amount),0) FROM fines f WHERE f.member_id = m.id AND f.status = "unpaid") AS fine_balance
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN members m ON m.user_id = u.id AND m.deleted_at IS NULL
             LEFT JOIN member_types mt ON mt.id = m.member_type_id
             WHERE u.id = :id AND u.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function getNormalizedUser(int $id): array
    {
        $row = $this->findUserById($id);
        if (!$row) {
            Response::error('User not found.', 404);
        }

        return AppNormalizer::user($row);
    }

    private function statusActionTarget(int $userId, int $adminId): array
    {
        if ($userId <= 0) {
            Response::error('A valid user_id is required.', 422);
        }
        if ($userId === $adminId) {
            Response::error('You cannot change your own account status.', 409);
        }

        $row = $this->findUserById($userId);
        if (!$row) {
            Response::error('User not found.', 404);
        }
        if (($row['role_slug'] ?? '') === 'admin') {
            $this->assertAnotherActiveAdminExists($userId);
        }

        return $row;
    }

    private function setAccountStatus(int $userId, int $adminId, string $status, array $metadata): void
    {
        $allowed = ['active', 'pending', 'rejected', 'suspended', 'deactivated'];
        if (!in_array($status, $allowed, true)) {
            Response::error('Invalid account status.', 422);
        }

        $assignments = ['status = :status', 'updated_by = :admin_id', 'updated_at = NOW()'];
        $userParams = [
            'status' => $status,
            'admin_id' => $adminId,
            'user_id' => $userId,
        ];
        foreach ($metadata as $column => $value) {
            if ($value === ':admin_id') {
                $param = $column . '_admin_id';
                $assignments[] = $column . ' = :' . $param;
                $userParams[$param] = $adminId;
                continue;
            }
            $assignments[] = $column . ' = ' . $value;
        }
        $memberParams = [
            'status' => $status,
            'admin_id' => $adminId,
            'user_id' => $userId,
        ];

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE users SET ' . implode(', ', $assignments) . ' WHERE id = :user_id')->execute($userParams);
            $this->db->prepare(
                'UPDATE members SET status = :status, updated_by = :admin_id, updated_at = NOW() WHERE user_id = :user_id AND deleted_at IS NULL'
            )->execute($memberParams);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function loginStatusMessage(string $status): string
    {
        return match ($status) {
            'pending' => 'This account is waiting for admin approval.',
            'rejected' => 'This account registration was rejected.',
            'suspended' => 'This account is suspended.',
            'deactivated', 'inactive' => 'This account is not active.',
            default => 'This account is not active.',
        };
    }

    private function assertUniqueLogin(string $username, string $email): void
    {
        $this->assertUniqueLoginForUser($username, $email, null);
    }

    private function assertStrongPassword(string $password): void
    {
        if (trim($password) === '') {
            Response::error('Password is required.', 422, ['password' => 'Password is required.']);
        }
        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters.', 422, ['password' => 'Password must be at least 8 characters.']);
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            Response::error('Password must contain at least one letter and one number.', 422, ['password' => 'Password must contain at least one letter and one number.']);
        }
    }

    private function assertUniqueLoginForUser(string $username, string $email, ?int $userId): void
    {
        $params = ['username' => trim($username), 'email' => trim($email)];
        $exclude = '';
        if ($userId !== null) {
            $exclude = ' AND id <> :user_id';
            $params['user_id'] = $userId;
        }
        $stmt = $this->db->prepare(
            'SELECT id FROM users
             WHERE (username = :username OR email = :email)
               ' . $exclude . '
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute($params);
        if ($stmt->fetch()) {
            Response::error('Username or email already exists.', 409);
        }
    }

    private function assignableRoleSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if (!in_array($slug, ['admin', 'member'], true)) {
            Response::error('Invalid role.', 422);
        }

        return $slug;
    }

    private function assertAnotherActiveAdminExists(int $excludedUserId): void
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.slug = 'admin'
               AND u.status IN ('active', 'approved')
               AND u.deleted_at IS NULL
               AND u.id <> :user_id"
        );
        $stmt->execute(['user_id' => $excludedUserId]);
        if ((int)$stmt->fetchColumn() === 0) {
            Response::error('At least one active administrator account must remain.', 409);
        }
    }

    private function ensureMemberRecord(int $userId, int $adminId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM members WHERE user_id = :user_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        if ($stmt->fetch()) {
            $this->db->prepare(
                'UPDATE members SET status = "active", updated_by = :admin_id, updated_at = NOW() WHERE user_id = :user_id AND deleted_at IS NULL'
            )->execute(['user_id' => $userId, 'admin_id' => $adminId]);
            return;
        }

        $memberNo = 'M-' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);
        $this->db->prepare(
            'INSERT INTO members (user_id, member_type_id, member_number, status, joined_at, created_by, updated_by, created_at, updated_at)
             VALUES (:user_id, :member_type_id, :member_number, "active", CURDATE(), :created_by, :updated_by, NOW(), NOW())'
        )->execute([
            'user_id' => $userId,
            'member_type_id' => $this->memberTypeId('student'),
            'member_number' => $memberNo,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);
    }

    private function roleId(string $slug): int
    {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (int)$stmt->fetchColumn();
    }

    private function memberTypeId(string $slug): int
    {
        $slug = in_array($slug, ['student', 'staff'], true) ? $slug : 'student';
        $stmt = $this->db->prepare('SELECT id FROM member_types WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (int)$stmt->fetchColumn();
    }
}
