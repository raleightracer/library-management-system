<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

try {
    Auth::requireLogin();
    $state = (new AppStateService())->bootstrap(Auth::user());
    $state['csrf_token'] = Auth::csrfToken();
    Response::success('State loaded.', $state);
} catch (Throwable $e) {
    Response::exception($e);
}
