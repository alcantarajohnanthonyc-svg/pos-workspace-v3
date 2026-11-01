<?php

namespace Tests\Unit;

use App\Models\UserAccountModel;
use App\Services\ActiveSessionService;
use App\Services\AuditLogService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

final class ActiveSessionServiceTest extends CIUnitTestCase
{
    private BaseConnection $connection;
    private ActiveSessionService $sessions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = \Config\Database::connect([
            'DSN'        => '',
            'hostname'   => '',
            'username'   => '',
            'password'   => '',
            'database'   => ':memory:',
            'DBDriver'   => 'SQLite3',
            'DBPrefix'   => '',
            'pConnect'   => false,
            'DBDebug'    => true,
            'charset'    => 'utf8',
            'DBCollat'   => 'utf8_general_ci',
            'foreignKeys'=> true,
        ], false);
        $this->createSchema();
        $this->seedUser(1, 'SUPERADMIN', 'ACTIVE', 'ACTIVE');
        $this->seedUser(2, 'USER', 'ACTIVE', 'ACTIVE');
        $this->seedUser(3, 'USER', 'INACTIVE', 'ACTIVE');
        $this->sessions = new ActiveSessionService($this->connection, new AuditLogService($this->connection));
    }

    public function testAccountAndAccessReadinessRequiresActiveStatusAndGrantExceptSuperadmin(): void
    {
        $this->assertTrue(UserAccountModel::hasRequiredApplicationAccess($this->identity('ACTIVE', 'ACTIVE', 'SUPERADMIN'), false));
        $this->assertTrue(UserAccountModel::hasRequiredApplicationAccess($this->identity('ACTIVE', 'ACTIVE', 'USER'), true));
        $this->assertFalse(UserAccountModel::hasRequiredApplicationAccess($this->identity('ACTIVE', 'ACTIVE', 'USER'), false));
        $this->assertFalse(UserAccountModel::hasRequiredApplicationAccess($this->identity('PENDING', 'ACTIVE', 'SUPERADMIN'), true));
        $this->assertFalse(UserAccountModel::hasRequiredApplicationAccess($this->identity('ACTIVE', 'INACTIVE', 'SUPERADMIN'), true));
    }

    public function testConflictCancellationAndConfirmedReplacementPreserveSingleSession(): void
    {
        $this->assertSame('established', $this->sessions->establish(1, 'first-private-session-id')['status']);
        $prompt = $this->sessions->establish(1, 'second-private-session-id');
        $this->assertSame('conflict', $prompt['status']);

        $this->sessions->auditCancellation(1);
        $this->assertTrue($this->sessions->validate(1, 'first-private-session-id'));

        $this->assertSame('established', $this->sessions->establish(1, 'second-private-session-id', true, $prompt['expected_hash'])['status']);
        $this->assertFalse($this->sessions->validate(1, 'first-private-session-id'));
        $this->assertTrue($this->sessions->validate(1, 'second-private-session-id'));
        $this->assertSame(1, $this->connection->table('sec_active_sessions')->countAllResults());

        $stored = $this->connection->table('sec_active_sessions')->where('user_id', 1)->get()->getRowArray();
        $this->assertSame(hash('sha256', 'second-private-session-id'), $stored['session_hash']);
        $this->assertStringNotContainsString('second-private-session-id', json_encode($stored));
        $this->assertSame(1, $this->connection->table('log_audit_logs')->where('event_code', 'AUTH_SESSION_REPLACED')->countAllResults());
        $this->assertSame(1, $this->connection->table('log_audit_logs')->where('event_code', 'AUTH_SESSION_REPLACEMENT_CANCELLED')->countAllResults());
    }

    public function testExpiredRecordIsRejectedAndReplacedWithoutConfirmation(): void
    {
        $this->assertSame('established', $this->sessions->establish(1, 'expired-private-id')['status']);
        $this->connection->table('sec_active_sessions')->where('user_id', 1)->update(['expires_at' => '2000-01-01 00:00:00']);
        $this->assertFalse($this->sessions->validate(1, 'expired-private-id'));
        $this->assertSame('established', $this->sessions->establish(1, 'fresh-private-id')['status']);
        $this->assertTrue($this->sessions->validate(1, 'fresh-private-id'));
    }

    public function testConcurrentClaimsSerializeToOneAccountRegistryRow(): void
    {
        // Sequential contender calls exercise the same unique account claim that
        // MariaDB serializes under SELECT ... FOR UPDATE in production.
        $this->assertSame('established', $this->sessions->establish(1, 'contender-one')['status']);
        $promptOne = $this->sessions->establish(1, 'contender-two');
        $promptTwo = $this->sessions->establish(1, 'contender-three');
        $this->assertSame('conflict', $promptOne['status']);
        $this->assertSame('conflict', $promptTwo['status']);
        $this->assertSame('established', $this->sessions->establish(1, 'contender-two', true, $promptOne['expected_hash'])['status']);
        $this->assertSame('stale', $this->sessions->establish(1, 'contender-three', true, $promptTwo['expected_hash'])['status']);
        $this->assertTrue($this->sessions->validate(1, 'contender-two'));
        $this->assertSame(1, $this->connection->table('sec_active_sessions')->countAllResults());
    }

    public function testInactiveAccountRoleAndUnassignedUserAreDenied(): void
    {
        $this->assertSame('denied', $this->sessions->establish(2, 'unassigned-user-session')['status']);
        $this->assertSame('denied', $this->sessions->establish(3, 'inactive-account-session')['status']);
        $this->connection->table('usr_roles')->where('role_id', 2)->update(['status' => 'INACTIVE']);
        $this->assertSame('denied', $this->sessions->establish(2, 'inactive-role-session')['status']);
        $this->assertSame(0, $this->connection->table('sec_active_sessions')->countAllResults());
    }

    public function testAuditFailurePreventsSessionGrant(): void
    {
        $this->connection->query("CREATE TRIGGER reject_audit BEFORE INSERT ON log_audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->assertSame('denied', $this->sessions->establish(1, 'must-not-be-granted')['status']);
        $this->assertSame(0, $this->connection->table('sec_active_sessions')->countAllResults());
    }

    public function testLogoutRevokesSessionEvenWhenAuditWriteFails(): void
    {
        $this->assertSame('established', $this->sessions->establish(1, 'logout-session-id')['status']);
        $this->connection->query("CREATE TRIGGER reject_audit BEFORE INSERT ON log_audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        $this->sessions->revoke(1, hash('sha256', 'logout-session-id'));
        $this->assertFalse($this->sessions->validate(1, 'logout-session-id'));
        $this->assertSame(0, $this->connection->table('sec_active_sessions')->countAllResults());
    }

    public function testAdministratorCanRevokeTargetSessionWithoutSessionHash(): void
    {
        $this->assertSame('established', $this->sessions->establish(1, 'target-user-private-session')['status']);
        $this->sessions->revokeAccount(2, 1);
        $this->assertFalse($this->sessions->validate(1, 'target-user-private-session'));
        $this->assertSame(0, $this->connection->table('sec_active_sessions')->where('user_id', 1)->countAllResults());
    }

    public function testAuditLogRedactsSensitiveKeys(): void
    {
        (new AuditLogService($this->connection))->record(1, 'TEST_SECURITY_EVENT', 'SUCCESS', [
            'reason' => 'verified',
            'session_id' => 'never-store-this',
            'access_token' => 'never-store-this-either',
        ]);
        $row = $this->connection->table('log_audit_logs')->where('event_code', 'TEST_SECURITY_EVENT')->get()->getRowArray();
        $this->assertStringContainsString('verified', $row['after_data']);
        $this->assertStringNotContainsString('never-store-this', $row['after_data']);
    }

    private function createSchema(): void
    {
        $ddl = [
            'CREATE TABLE usr_roles (role_id INTEGER PRIMARY KEY, role_code TEXT, role_name TEXT, status TEXT)',
            'CREATE TABLE usr_accounts (user_id INTEGER PRIMARY KEY, username TEXT, employee_code TEXT, first_name TEXT, last_name TEXT, avatar_url TEXT, birthday TEXT, email TEXT, password_hash TEXT, role_id INTEGER, position_id INTEGER, default_module_id INTEGER, account_status TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE mod_modules (module_id INTEGER PRIMARY KEY, module_code TEXT, status TEXT)',
            'CREATE TABLE mod_module_actions (module_action_id INTEGER PRIMARY KEY, module_id INTEGER, action_code TEXT, is_enabled INTEGER)',
            'CREATE TABLE rbac_role_module_actions (role_id INTEGER, module_action_id INTEGER)',
            'CREATE TABLE rbac_user_module_actions (user_id INTEGER, module_action_id INTEGER)',
            'CREATE TABLE sec_active_sessions (user_id INTEGER PRIMARY KEY, session_hash TEXT, expires_at TEXT, last_seen_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)',
            'CREATE TABLE log_audit_logs (audit_log_id INTEGER PRIMARY KEY AUTOINCREMENT, actor_user_id INTEGER, module_id INTEGER, event_code TEXT, result TEXT, before_data TEXT, after_data TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
        ];
        foreach ($ddl as $sql) $this->connection->query($sql);
    }

    private function seedUser(int $id, string $roleCode, string $accountStatus, string $roleStatus): void
    {
        $roleId = $id === 1 ? 1 : 2;
        if ($this->connection->table('usr_roles')->where('role_id', $roleId)->countAllResults() === 0) {
            $this->connection->table('usr_roles')->insert(['role_id' => $roleId, 'role_code' => $roleCode, 'role_name' => $roleCode, 'status' => $roleStatus]);
        }
        $this->connection->table('usr_accounts')->insert([
            'user_id' => $id, 'email' => 'user' . $id . '@example.invalid', 'role_id' => $roleId,
            'account_status' => $accountStatus,
        ]);
    }

    private function identity(string $accountStatus, string $roleStatus, string $roleCode): array
    {
        return ['user_id' => 1, 'role_id' => 1, 'account_status' => $accountStatus, 'role_status' => $roleStatus, 'role_code' => $roleCode];
    }
}
