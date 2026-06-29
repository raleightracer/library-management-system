<?php
declare(strict_types=1);

final class AuditService extends BaseModel
{
    public function log(?int $userId, string $action, string $entityType, ?int $entityId = null, array $meta = []): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, metadata, ip_address, user_agent)
             VALUES (:user_id, :action, :entity_type, :entity_id, :metadata, :ip_address, :user_agent)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }
}
