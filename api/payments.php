<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

function paymongoRequest(string $endpoint, array $payload): array
{
    if (!defined('PAYMONGO_SECRET_KEY') || PAYMONGO_SECRET_KEY === '' || PAYMONGO_SECRET_KEY === 'REPLACE_WITH_YOUR_SECRET_KEY') {
        Response::error('PayMongo secret key is not configured.', 500);
    }

    $ch = curl_init('https://api.paymongo.com' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $error !== '') {
        throw new RuntimeException('Unable to connect to PayMongo.');
    }

    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('PayMongo returned an invalid response.');
    }

    if ($status < 200 || $status >= 300) {
        $detail = $json['errors'][0]['detail'] ?? $json['errors'][0]['title'] ?? 'PayMongo request failed.';
        throw new RuntimeException((string)$detail);
    }

    return $json;
}

try {
    $current = Auth::requireLogin();
    $input = Request::input();
    Auth::requireCsrfToken($input);
    Request::requireFields($input, ['fine_id']);

    $fineId = (int)$input['fine_id'];
    $db = Database::connection();

    $stmt = $db->prepare(
        "SELECT f.id, f.member_id, f.amount, f.reason, f.status, m.user_id, u.email, u.first_name, u.last_name
         FROM fines f
         INNER JOIN members m ON m.id = f.member_id
         INNER JOIN users u ON u.id = m.user_id
         WHERE f.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $fineId]);
    $fine = $stmt->fetch();

    if (!$fine) {
        Response::error('Fine not found.', 404);
    }

    if (($fine['status'] ?? '') !== 'unpaid') {
        Response::error('This fine is already paid or no longer payable.', 422);
    }

    if (($current['role_slug'] ?? '') !== 'admin' && (int)$fine['member_id'] !== (int)($current['member_id'] ?? 0)) {
        Response::error('You can only pay your own fines.', 403);
    }

    $amount = (float)$fine['amount'];
    if ($amount <= 0) {
        Response::error('Fine amount is invalid.', 422);
    }

    $amountInCentavos = (int)round($amount * 100);
    if ($amountInCentavos < 2000) {
        Response::error('PayMongo requires a minimum payment of PHP 20.00.', 422);
    }

    $db->beginTransaction();

    $lockFine = $db->prepare("SELECT id, status FROM fines WHERE id = :id FOR UPDATE");
    $lockFine->execute(['id' => $fineId]);
    $lockedFine = $lockFine->fetch();
    if (!$lockedFine || ($lockedFine['status'] ?? '') !== 'unpaid') {
        $db->rollBack();
        Response::error('This fine is already paid or no longer payable.', 422);
    }

    $pending = $db->prepare(
        "SELECT id, checkout_url
         FROM payments
         WHERE fine_id = :fine_id AND status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );
    $pending->execute(['fine_id' => $fineId]);
    $existingPayment = $pending->fetch();

    if ($existingPayment) {
        $db->commit();
        if (!empty($existingPayment['checkout_url'])) {
            Response::success('Existing pending payment checkout returned.', [
                'checkout_url' => $existingPayment['checkout_url'],
                'payment_id' => (string)$existingPayment['id'],
                'reused' => true,
            ]);
        }

        Response::error('A pending payment already exists for this fine. Please try again shortly.', 409);
    }

    $localReference = 'lms_pay_' . bin2hex(random_bytes(16));
    $insert = $db->prepare(
        "INSERT INTO payments (fine_id, amount, status, provider, local_reference)
         VALUES (:fine_id, :amount, 'pending', 'paymongo', :local_reference)"
    );
    $insert->execute([
        'fine_id' => $fineId,
        'amount' => $amount,
        'local_reference' => $localReference,
    ]);
    $paymentId = (int)$db->lastInsertId();

    $db->commit();

    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
        . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . ($basePath === '' ? '' : $basePath);

    $payload = [
        'data' => [
            'attributes' => [
                'description' => 'QuadByte LMS fine #' . $fineId,
                'send_email_receipt' => true,
                'show_description' => true,
                'show_line_items' => true,
                'success_url' => $baseUrl . '/index.php',
                'cancel_url' => $baseUrl . '/index.php',
                'payment_method_types' => ['card', 'gcash', 'grab_pay', 'paymaya'],
                'line_items' => [[
                    'currency' => 'PHP',
                    'amount' => $amountInCentavos,
                    'name' => 'Library Fine #' . $fineId,
                    'quantity' => 1,
                    'description' => (string)$fine['reason'],
                ]],
                'metadata' => [
                    'payment_id' => (string)$paymentId,
                    'local_payment_reference' => $localReference,
                    'fine_id' => (string)$fineId,
                    'member_id' => (string)$fine['member_id'],
                ],
            ],
        ],
    ];

    $result = paymongoRequest('/v1/checkout_sessions', $payload);
    $session = $result['data'] ?? [];
    $referenceId = $session['id'] ?? null;
    $checkoutUrl = $session['attributes']['checkout_url'] ?? null;

    if (!$referenceId || !$checkoutUrl) {
        Response::error('PayMongo did not return a checkout URL.', 502);
    }

    $update = $db->prepare('UPDATE payments SET reference_id = :reference_id, checkout_url = :checkout_url WHERE id = :id');
    $update->execute([
        'reference_id' => $referenceId,
        'checkout_url' => $checkoutUrl,
        'id' => $paymentId,
    ]);

    Response::success('Payment checkout created.', [
        'checkout_url' => $checkoutUrl,
        'payment_id' => (string)$paymentId,
        'reused' => false,
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    if (isset($db, $paymentId) && $db instanceof PDO && (int)$paymentId > 0) {
        $failed = $db->prepare("UPDATE payments SET status = 'failed' WHERE id = :id AND status = 'pending' AND checkout_url IS NULL");
        $failed->execute(['id' => (int)$paymentId]);
    }

    Response::exception($e);
}
