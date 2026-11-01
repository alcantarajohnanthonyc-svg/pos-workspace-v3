<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class AuditLogService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @param array<string, mixed>|null $details */
    public function record(?int $actorUserId, string $eventCode, string $result, ?array $details = null, ?int $targetUserId = null): void
    {
        if (!in_array($result, ['SUCCESS', 'DENIED', 'FAILURE'], true)) {
            throw new RuntimeException('Unsupported audit result.');
        }

        $safeDetails = $details === null ? null : $this->redact($details);
        $event = [
            'actor_user_id' => $actorUserId,
            'module_id'     => null,
            'event_code'    => substr($eventCode, 0, 80),
            'result'        => $result,
            'before_data'   => null,
            'after_data'    => $safeDetails === null ? null : json_encode($safeDetails, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ];
        if ($targetUserId !== null && $this->db->fieldExists('target_user_id', 'log_audit_logs')) {
            $event['target_user_id'] = $targetUserId;
        }
        $inserted = $this->db->table('log_audit_logs')->insert($event);

        if (!$inserted || !$this->db->transStatus()) {
            throw new RuntimeException('The security event could not be audited.');
        }
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function recordChange(?int $actorUserId, string $eventCode, string $result, ?array $before, ?array $after, ?int $targetUserId): void
    {
        if (!in_array($result, ['SUCCESS', 'DENIED', 'FAILURE'], true)) {
            throw new RuntimeException('Unsupported audit result.');
        }
        $event = [
            'actor_user_id' => $actorUserId,
            'module_id' => null,
            'event_code' => substr($eventCode, 0, 80),
            'result' => $result,
            'before_data' => $before === null ? null : json_encode($this->redact($before), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'after_data' => $after === null ? null : json_encode($this->redact($after), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        ];
        if ($targetUserId !== null && $this->db->fieldExists('target_user_id', 'log_audit_logs')) {
            $event['target_user_id'] = $targetUserId;
        }
        if (!$this->db->table('log_audit_logs')->insert($event) || !$this->db->transStatus()) {
            throw new RuntimeException('The account change could not be audited.');
        }
    }

    private function redact(array $details): array
    {
        $safe = [];
        foreach ($details as $key => $value) {
            if (preg_match('/password|secret|token|session|credential|authorization|cookie/i', (string) $key)) {
                continue;
            }
            if (is_array($value)) {
                $value = $this->redact($value);
            } elseif (!is_scalar($value) && $value !== null) {
                continue;
            }
            $safe[$key] = is_string($value) ? mb_substr($value, 0, 200, 'UTF-8') : $value;
        }

        return $safe;
    }
}
