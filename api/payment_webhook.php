<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

function paymongoSignatureHeader(): string
{
    if (!empty($_SERVER['HTTP_PAYMONGO_SIGNATURE'])) {
        return (string)$_SERVER['HTTP_PAYMONGO_SIGNATURE'];
    }

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower((string)$name) === 'paymongo-signature') {
                return (string)$value;
            }
        }
    }

    return '';
}

function verifyPaymongoWebhookSignature(string $raw): void
{
    if (!defined('PAYMONGO_WEBHOOK_SECRET') || PAYMONGO_WEBHOOK_SECRET === '' || PAYMONGO_WEBHOOK_SECRET === 'REPLACE_WITH_YOUR_WEBHOOK_SECRET') {
        Response::error('PayMongo webhook secret is not configured.', 500);
    }

    $header = paymongoSignatureHeader();
    if ($header === '') {
        Response::error('Missing PayMongo signature.', 401);
    }

    $parts = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key !== '') {
            $parts[$key] = $value;
        }
    }

    $timestamp = $parts['t'] ?? '';
    if ($timestamp === '' || !ctype_digit($timestamp)) {
        Response::error('Invalid PayMongo signature timestamp.', 401);
    }

    if (abs(time() - (int)$timestamp) > 300) {
        Response::error('Expired PayMongo webhook signature.', 401);
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $raw, PAYMONGO_WEBHOOK_SECRET);
    $testSignature = $parts['te'] ?? '';
    $liveSignature = $parts['li'] ?? '';

    if (($testSignature === '' || !hash_equals($expected, $testSignature))
        && ($liveSignature === '' || !hash_equals($expected, $liveSignature))) {
        Response::error('Invalid PayMongo signature.', 401);
    }
}

function paymongoResourceAttributes(array $resource): array
{
    return is_array($resource['attributes'] ?? null) ? $resource['attributes'] : [];
}

function paymongoMetadata(array $resource): array
{
    $attributes = paymongoResourceAttributes($resource);
    $candidates = [$attributes['metadata'] ?? null];

    foreach (($attributes['payments'] ?? []) as $payment) {
        $candidates[] = $payment['attributes']['metadata'] ?? null;
    }

    foreach ($candidates as $candidate) {
        if (is_array($candidate) && $candidate !== []) {
            return $candidate;
        }
    }

    return [];
}

function paymongoReferenceCandidates(array $resource): array
{
    $attributes = paymongoResourceAttributes($resource);
    $candidates = [
        $resource['id'] ?? null,
        $attributes['checkout_session_id'] ?? null,
        $attributes['payment_intent_id'] ?? null,
        $attributes['source']['id'] ?? null,
    ];

    foreach (($attributes['payments'] ?? []) as $payment) {
        $candidates[] = $payment['id'] ?? null;
    }

    return array_values(array_unique(array_filter(array_map(
        static fn($value): string => trim((string)$value),
        $candidates
    ))));
}

function paymongoPaidAmount(array $resource): ?int
{
    $attributes = paymongoResourceAttributes($resource);
    if (isset($attributes['amount'])) {
        return (int)$attributes['amount'];
    }

    foreach (($attributes['payments'] ?? []) as $payment) {
        if (isset($payment['attributes']['amount'])) {
            return (int)$payment['attributes']['amount'];
        }
    }

    return null;
}

function findWebhookPayment(PDO $db, int $paymentId, string $localReference, ?int $fineId, ?int $paidAmount, array $referenceCandidates): ?array
{
    if ($localReference !== '') {
        $stmt = $db->prepare("SELECT * FROM payments WHERE local_reference = :local_reference LIMIT 1");
        $stmt->execute(['local_reference' => $localReference]);
        return $stmt->fetch() ?: null;
    }

    if ($paymentId > 0) {
        $stmt = $db->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $paymentId]);
        return $stmt->fetch() ?: null;
    }

    foreach ($referenceCandidates as $referenceId) {
        $stmt = $db->prepare("SELECT * FROM payments WHERE reference_id = :reference_id LIMIT 1");
        $stmt->execute(['reference_id' => $referenceId]);
        $payment = $stmt->fetch();
        if ($payment) {
            return $payment;
        }
    }

    if ($fineId && $paidAmount !== null) {
        $stmt = $db->prepare(
            "SELECT * FROM payments
             WHERE fine_id = :fine_id
               AND amount = :amount
               AND status = 'pending'
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            'fine_id' => $fineId,
            'amount' => $paidAmount / 100,
        ]);
        return $stmt->fetch() ?: null;
    }

    return null;
}

try {
    $raw = file_get_contents('php://input') ?: '';
    verifyPaymongoWebhookSignature($raw);

    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        Response::error('Invalid webhook payload.', 400);
    }

    $eventType = $payload['data']['attributes']['type'] ?? '';
    $paidEvents = ['payment.paid', 'checkout_session.payment.paid'];
    $failedEvents = ['payment.failed', 'checkout_session.payment.failed', 'checkout_session.expired'];
    if (!in_array($eventType, array_merge($paidEvents, $failedEvents), true)) {
        Response::success('Webhook ignored.');
    }

    $resource = $payload['data']['attributes']['data'] ?? [];
    $referenceCandidates = paymongoReferenceCandidates($resource);
    $referenceId = $referenceCandidates[0] ?? null;
    $metadata = paymongoMetadata($resource);
    $paymentId = isset($metadata['payment_id']) ? (int)$metadata['payment_id'] : 0;
    $fineId = isset($metadata['fine_id']) ? (int)$metadata['fine_id'] : null;
    $localReference = trim((string)($metadata['local_payment_reference'] ?? ''));
    $paidAmount = paymongoPaidAmount($resource);

    $db = Database::connection();
    new UserPreferenceService($db);
    $notifications = new NotificationService($db);

    $payment = findWebhookPayment($db, $paymentId, $localReference, $fineId, $paidAmount, $referenceCandidates);
    if (!$payment) {
        Response::success('No pending payment found for this webhook.');
    }

    if (in_array($eventType, $failedEvents, true)) {
        if (($payment['status'] ?? '') === 'pending') {
            $failed = $db->prepare("UPDATE payments SET status = 'failed' WHERE id = :id AND status = 'pending'");
            $failed->execute(['id' => (int)$payment['id']]);
        }
        Response::success('Payment failure webhook processed.');
    }

    if (($payment['status'] ?? '') === 'paid') {
        Response::success('Payment webhook already processed.');
    }

    if (($payment['status'] ?? '') !== 'pending') {
        Response::success('Payment is not pending; webhook ignored.');
    }

    if ($paidAmount !== null && (int)round((float)$payment['amount'] * 100) !== $paidAmount) {
        Response::error('Webhook amount does not match the pending payment.', 400);
    }

    $db->beginTransaction();

    $lock = $db->prepare("SELECT * FROM payments WHERE id = :id FOR UPDATE");
    $lock->execute(['id' => (int)$payment['id']]);
    $payment = $lock->fetch();

    if (!$payment || ($payment['status'] ?? '') === 'paid') {
        $db->commit();
        Response::success('Payment webhook already processed.');
    }

    if (($payment['status'] ?? '') !== 'pending') {
        $db->commit();
        Response::success('Payment is not pending; webhook ignored.');
    }

    $fineLock = $db->prepare(
        "SELECT f.id, f.status, m.user_id
         FROM fines f
         INNER JOIN members m ON m.id = f.member_id
         WHERE f.id = :id
         LIMIT 1
         FOR UPDATE"
    );
    $fineLock->execute(['id' => (int)$payment['fine_id']]);
    $fine = $fineLock->fetch();
    if (!$fine || ($fine['status'] ?? '') !== 'unpaid') {
        $db->commit();
        Response::error('Fine is no longer payable for this payment.', 409);
    }

    $paidPayment = $db->prepare(
        "UPDATE payments
         SET status = 'paid', reference_id = COALESCE(reference_id, :reference_id)
         WHERE id = :id AND status = 'pending'"
    );
    $paidPayment->execute([
        'reference_id' => $referenceId,
        'id' => (int)$payment['id'],
    ]);
    if ($paidPayment->rowCount() !== 1) {
        Response::error('Payment state could not be confirmed.', 409);
    }

    $paidFine = $db->prepare(
        "UPDATE fines
         SET status = 'paid', paid_at = NOW(), paid_by = :paid_by, updated_by = :updated_by, updated_at = NOW()
         WHERE id = :id AND status = 'unpaid'"
    );
    $paidFine->execute([
        'paid_by' => (int)($fine['user_id'] ?? 0) ?: null,
        'updated_by' => (int)($fine['user_id'] ?? 0) ?: null,
        'id' => (int)$payment['fine_id'],
    ]);
    if ($paidFine->rowCount() !== 1) {
        Response::error('Fine state could not be confirmed.', 409);
    }

    (new AuditService($db))->log((int)($fine['user_id'] ?? 0) ?: null, 'online_payment', 'fines', (int)$payment['fine_id'], [
        'payment_id' => (int)$payment['id'],
        'provider' => $payment['provider'] ?? 'paymongo',
    ]);
    $notifications->create((int)$fine['user_id'], null, 'Fine payment confirmed', 'Your online fine payment has been confirmed.', 'info', null, 'fine', (int)$payment['fine_id'], 'paid');

    $db->commit();

    Response::success('Payment confirmed.');
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log('PayMongo webhook error: ' . $e->getMessage());
    Response::exception($e);
}
