<?php
declare(strict_types=1);

final class AppStateService extends BaseModel
{
    public function bootstrap(array $currentUser): array
    {
        $timingEnabled = (bool)(Database::config()['app']['debug'] ?? false);
        $totalStart = microtime(true);
        $time = function (string $section, callable $callback) use ($timingEnabled): mixed {
            $start = microtime(true);
            try {
                return $callback();
            } finally {
                if ($timingEnabled) {
                    error_log(sprintf('[state timing] %s: %dms', $section, (int)round((microtime(true) - $start) * 1000)));
                }
            }
        };

        $auth = new AuthService($this->db);
        $circulation = new CirculationService($this->db);
        $fine = new FineService($this->db);
        $notifications = new NotificationService($this->db);
        $preferences = new UserPreferenceService($this->db);

        $users = $time('users', fn() => ($currentUser['role_slug'] ?? '') === 'admin'
            ? $auth->listMembers()
            : [$auth->currentUser()]);

        try {
            return [
                'currentUser' => $time('current_user', fn() => $auth->currentUser()),
                'books' => $time('books', fn() => (new BookService($this->db))->list()),
                'users' => array_values(array_filter($users)),
                'transactions' => $time('transactions', fn() => $circulation->listLoans($currentUser)),
                'requests' => $time('borrow_requests', fn() => $circulation->listRequests($currentUser)),
                'reservations' => $time('reservations', fn() => $circulation->listReservations($currentUser)),
                'fines' => $time('fines', fn() => $fine->list($currentUser)),
                'fine_rules' => $time('fine_rules', fn() => $fine->listFineRules()),
                'notifications' => $time('notifications', fn() => $notifications->listForCurrentUser($currentUser)),
                'preferences' => $time('preferences', fn() => $preferences->getForUser((int)$currentUser['id'])),
                'categories' => $time('categories', fn() => (new ReferenceService($this->db))->list('categories')),
                'authors' => $time('authors', fn() => (new ReferenceService($this->db))->list('authors')),
                'publishers' => $time('publishers', fn() => (new ReferenceService($this->db))->list('publishers')),
                'settings' => Database::config()['app'],
            ];
        } finally {
            if ($timingEnabled) {
                error_log(sprintf('[state timing] total: %dms', (int)round((microtime(true) - $totalStart) * 1000)));
            }
        }
    }
}
