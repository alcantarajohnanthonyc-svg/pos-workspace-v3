<?php

namespace App\Services;

use App\Models\UserAccountModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class ActiveSessionService
{
    private const SESSION_LIFETIME = 7200;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditLogService $audit,
    ) {
    }

    /** @return array{status: string, expected_hash: string|null} */
    public function establish(int $userId, string $sessionId, bool $replace = false, ?string $expectedCurrentHash = null): array
    {
        if ($userId < 1 || $sessionId === '') {
            return ['status' => 'denied', 'expected_hash' => null];
        }

        $this->db->transBegin();
        try {
            // The account row exists before the registry row. Locking it gives every
            // login/replacement attempt for this account one serialization point.
            $lockClause = $this->db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
            $account = $this->db->query(
                'SELECT `user_id` FROM `usr_accounts` WHERE `user_id` = ?' . $lockClause,
                [$userId],
            )->getRowArray();

            if (!$account) {
                $this->audit->record(null, 'AUTH_LOGIN_DENIED', 'DENIED', ['reason' => 'account_unavailable']);
                $this->commitOrFail();

                return ['status' => 'denied', 'expected_hash' => null];
            }

            $accounts = new UserAccountModel($this->db);
            $user = $accounts->findForSession($userId);
            if ($user === null || !$accounts->isApplicationReady($user)) {
                $this->audit->record($userId, 'AUTH_LOGIN_DENIED', 'DENIED', ['reason' => 'account_not_ready']);
                $this->commitOrFail();

                return ['status' => 'denied', 'expected_hash' => null];
            }

            $active = $this->db->query(
                'SELECT `session_hash`, `expires_at` FROM `sec_active_sessions` WHERE `user_id` = ?' . $lockClause,
                [$userId],
            )->getRowArray();
            $now = time();
            $isActive = $active !== null && strtotime((string) $active['expires_at']) > $now;

            if ($isActive && !$replace) {
                $this->audit->record($userId, 'AUTH_SESSION_CONFLICT', 'DENIED');
                $this->commitOrFail();

                return ['status' => 'conflict', 'expected_hash' => (string) $active['session_hash']];
            }

            if ($replace && $active !== null
                && (!is_string($expectedCurrentHash)
                    || !preg_match('/^[a-f0-9]{64}$/', $expectedCurrentHash)
                    || !hash_equals((string) $active['session_hash'], $expectedCurrentHash))) {
                $this->audit->record($userId, 'AUTH_SESSION_REPLACEMENT_STALE', 'DENIED');
                $this->commitOrFail();

                return ['status' => 'stale', 'expected_hash' => null];
            }

            $hash = hash('sha256', $sessionId);
            $expires = date('Y-m-d H:i:s', $now + self::SESSION_LIFETIME);
            $seen = date('Y-m-d H:i:s', $now);
            $sql = $this->db->DBDriver === 'SQLite3'
                ? 'INSERT INTO sec_active_sessions (user_id, session_hash, expires_at, last_seen_at) VALUES (?, ?, ?, ?) '
                    . 'ON CONFLICT(user_id) DO UPDATE SET session_hash = excluded.session_hash, expires_at = excluded.expires_at, last_seen_at = excluded.last_seen_at'
                : 'INSERT INTO `sec_active_sessions` (`user_id`, `session_hash`, `expires_at`, `last_seen_at`) VALUES (?, ?, ?, ?) '
                    . 'ON DUPLICATE KEY UPDATE `session_hash` = VALUES(`session_hash`), `expires_at` = VALUES(`expires_at`), `last_seen_at` = VALUES(`last_seen_at`)';
            $this->db->query($sql, [$userId, $hash, $expires, $seen]);

            $event = $isActive ? 'AUTH_SESSION_REPLACED' : (($active !== null) ? 'AUTH_EXPIRED_SESSION_REPLACED' : 'AUTH_LOGIN_SUCCESS');
            $this->audit->record($userId, $event, 'SUCCESS', ['role_code' => (string) $user['role_code']]);
            $this->commitOrFail();

            return ['status' => 'established', 'expected_hash' => null];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            log_message('error', 'Active session establishment failed; no session was granted.');

            return ['status' => 'denied', 'expected_hash' => null];
        }
    }

    public function validate(int $userId, string $sessionId): bool
    {
        if ($userId < 1 || $sessionId === '') {
            return false;
        }

        $hash = hash('sha256', $sessionId);
        $now = date('Y-m-d H:i:s');
        $this->db->table('sec_active_sessions')
            ->where('user_id', $userId)
            ->where('session_hash', $hash)
            ->where('expires_at >', $now)
            ->update(['last_seen_at' => $now, 'expires_at' => date('Y-m-d H:i:s', time() + self::SESSION_LIFETIME)]);

        $stillCurrent = $this->db->table('sec_active_sessions')
            ->where('user_id', $userId)
            ->where('session_hash', $hash)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->countAllResults() === 1;
        if (!$stillCurrent) {
            try {
                $this->audit->record($userId, 'AUTH_SESSION_ACCESS_DENIED', 'DENIED', ['reason' => 'expired_or_replaced']);
            } catch (\Throwable $exception) {
                log_message('error', 'Rejected session audit insert failed.');
            }
            return false;
        }

        $user = (new UserAccountModel($this->db))->findForSession($userId);
        if ($user !== null && $user['account_status'] === 'ACTIVE' && $user['role_status'] === 'ACTIVE'
            && (new UserAccountModel($this->db))->hasApplicationAccess($user)) {
            return true;
        }

        $this->revoke($userId, $hash, 'AUTH_ACCESS_REVOKED');

        return false;
    }

    public function revoke(int $userId, string $sessionHash, string $event = 'AUTH_LOGOUT'): void
    {
        if ($userId < 1 || !preg_match('/^[a-f0-9]{64}$/', $sessionHash)) {
            return;
        }

        $this->db->transBegin();
        try {
            $deleted = $this->db->table('sec_active_sessions')->where(['user_id' => $userId, 'session_hash' => $sessionHash])->delete();
            $this->commitOrFail();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            log_message('error', 'Active session revocation failed; session remains blocked by registry validation.');
            throw new RuntimeException('Sign out could not be completed safely.');
        }

        // Revocation remains committed even if the audit store is unavailable.
        // Authentication/session establishment remains fail-closed on audit failure.
        if ($deleted) {
            try {
                $this->audit->record($userId, $event, 'SUCCESS');
            } catch (\Throwable $exception) {
                log_message('error', 'Session revocation succeeded but its audit insert failed.');
            }
        }
    }

    /** Revoke the single registered session for an account without exposing its hash. */
    public function revokeAccount(int $actorUserId, int $targetUserId, string $event = 'AUTH_ACCOUNT_SESSION_REVOKED'): void
    {
        if ($actorUserId < 1 || $targetUserId < 1) {
            throw new RuntimeException('A valid actor and target account are required.');
        }

        $this->db->transBegin();
        try {
            $deleted = $this->db->table('sec_active_sessions')->where('user_id', $targetUserId)->delete();
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('The session revocation could not be committed.');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new RuntimeException('The account session could not be revoked safely.');
        }

        if ($deleted) {
            try {
                $this->audit->record($actorUserId, $event, 'SUCCESS', ['revoked' => true], $targetUserId);
            } catch (\Throwable $exception) {
                log_message('error', 'Account session revocation succeeded but its audit insert failed.');
            }
        }
    }

    public function auditCancellation(int $userId): void
    {
        $this->db->transBegin();
        try {
            $this->audit->record($userId, 'AUTH_SESSION_REPLACEMENT_CANCELLED', 'SUCCESS');
            $this->commitOrFail();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new RuntimeException('The cancellation event could not be audited.');
        }
    }

    private function commitOrFail(): void
    {
        if (!$this->db->transStatus() || !$this->db->transCommit()) {
            throw new RuntimeException('The session transaction could not be committed.');
        }
    }
}
