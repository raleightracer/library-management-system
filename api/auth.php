<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    $input = Request::input();
    $action = Request::action($input);

    switch ($action) {
        case 'check':
            $service = new AuthService();
            $user = $service->currentUser();
            Response::success('Session checked.', ['authenticated' => (bool)$user, 'user' => $user, 'csrf_token' => Auth::csrfToken()]);

        case 'login':
            $service = new AuthService();
            Request::requireFields($input, ['username', 'password']);
            $user = $service->login((string)$input['username'], (string)$input['password'], !empty($input['remember']));
            Response::success('Login successful.', ['user' => $user, 'csrf_token' => Auth::csrfToken()]);

        case 'signup':
            $service = new AuthService();
            $input['first_name'] = $input['first_name'] ?? $input['firstName'] ?? '';
            $input['last_name'] = $input['last_name'] ?? $input['lastName'] ?? '';
            $user = $service->signup($input);
            Response::success('Account created. Your registration is waiting for admin approval.', ['user' => $user, 'csrf_token' => Auth::csrfToken()], 201);

        case 'logout':
            Auth::requireCsrfToken($input);
            Auth::requireLogin();
            Auth::logout();
            Response::success('Logged out successfully.');

        case 'update_profile':
            Auth::requireCsrfToken($input);
            $service = new AuthService();
            $current = Auth::requireLogin();
            $payload = [
                'first_name' => $input['first_name'] ?? $input['firstName'] ?? null,
                'last_name' => $input['last_name'] ?? $input['lastName'] ?? null,
                'email' => $input['email'] ?? null,
                'avatar_url' => $input['avatar_url'] ?? $input['avatar'] ?? null,
                'phone' => $input['phone'] ?? null,
                'date_of_birth' => $input['date_of_birth'] ?? $input['dateOfBirth'] ?? $input['dob'] ?? null,
            ];
            $payload = array_filter($payload, static fn($value): bool => $value !== null);
            $user = $service->updateProfile($current, $payload);
            Response::success('Profile updated.', ['user' => $user]);

        case 'update_security':
            Auth::requireCsrfToken($input);
            $service = new AuthService();
            $current = Auth::requireLogin();
            $user = $service->updateSecurity($current, $input);
            Response::success('Account updated.', ['user' => $user]);

        default:
            Response::error('Invalid auth action.', 400);
    }
} catch (Throwable $e) {
    Response::exception($e);
}
