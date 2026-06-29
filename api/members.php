<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $admin = Auth::requireAdmin();
    $input = Request::input();
    $action = Request::action($input) ?: 'list';
    Request::requireCsrfForActions($action, $input, [
        'deactivate',
        'create_privileged',
        'update_role',
        'update_account',
        'approve_user',
        'reject_user',
        'suspend_user',
        'reactivate_user',
    ]);

    switch ($action) {
        case 'list':
            Response::success('Members loaded.', ['members' => (new AuthService())->listMembers($input['search'] ?? null)]);

        case 'deactivate':
            Request::requireFields($input, ['user_id']);
            $member = (new AuthService())->deactivateMember((int)$input['user_id'], (int)$admin['id'], $input['reason'] ?? null);
            Response::success('Member deactivated.', ['member' => $member]);

        case 'create_privileged':
            $result = (new AuthService())->createPrivilegedAccount($input, (int)$admin['id']);
            Response::success('Account created.', $result, 201);

        case 'update_role':
            Request::requireFields($input, ['user_id', 'role_slug']);
            $result = (new AuthService())->updateUserRole((int)$input['user_id'], (int)$admin['id'], (string)$input['role_slug']);
            Response::success('Role updated.', $result);

        case 'update_account':
            Request::requireFields($input, ['user_id']);
            $result = (new AuthService())->updateManagedAccount((int)$input['user_id'], (int)$admin['id'], $input);
            Response::success('Account updated.', $result);

        case 'approve_user':
            Request::requireFields($input, ['user_id']);
            $result = (new AuthService())->approveUser((int)$input['user_id'], (int)$admin['id']);
            Response::success('User approved.', $result);

        case 'reject_user':
            Request::requireFields($input, ['user_id']);
            $result = (new AuthService())->rejectUser((int)$input['user_id'], (int)$admin['id'], $input['reason'] ?? null);
            Response::success('User rejected.', $result);

        case 'suspend_user':
            Request::requireFields($input, ['user_id']);
            $result = (new AuthService())->suspendUser((int)$input['user_id'], (int)$admin['id'], $input['reason'] ?? null);
            Response::success('User suspended.', $result);

        case 'reactivate_user':
            Request::requireFields($input, ['user_id']);
            $result = (new AuthService())->reactivateUser((int)$input['user_id'], (int)$admin['id']);
            Response::success('User reactivated.', $result);

        default:
            Response::error('Invalid members action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
