<?php

namespace Tests\Unit;

use App\Services\AuditLogService;
use App\Services\UserLookupService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use DomainException;

final class UserLookupServiceTest extends CIUnitTestCase
{
    private BaseConnection $connection;
    private UserLookupService $lookups;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = Database::connect(['DSN'=>'','hostname'=>'','username'=>'','password'=>'','database'=>':memory:','DBDriver'=>'SQLite3','DBPrefix'=>'','pConnect'=>false,'DBDebug'=>true,'charset'=>'utf8','DBCollat'=>'utf8_general_ci'], false);
        $this->connection->query('CREATE TABLE org_positions (position_id INTEGER PRIMARY KEY AUTOINCREMENT,position_code TEXT,position_name TEXT,status TEXT,created_by INTEGER,created_at TEXT,updated_at TEXT)');
        $this->connection->query('CREATE TABLE org_departments (department_id INTEGER PRIMARY KEY AUTOINCREMENT,department_code TEXT,department_name TEXT,status TEXT,created_by INTEGER,created_at TEXT,updated_at TEXT)');
        $this->connection->query('CREATE TABLE log_audit_logs (audit_log_id INTEGER PRIMARY KEY AUTOINCREMENT,actor_user_id INTEGER,target_user_id INTEGER,module_id INTEGER,event_code TEXT,result TEXT,before_data TEXT,after_data TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
        $this->lookups = new UserLookupService($this->connection, new AuditLogService($this->connection));
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();
    }

    public function testCreateUpdateAndRetirePreserveLookupRecord(): void
    {
        $id = $this->lookups->save('positions', 1, ['code'=>'STORE_MANAGER','name'=>'Store Manager']);
        $this->lookups->save('positions', 1, ['code'=>'STORE_MANAGER','name'=>'Senior Store Manager'], $id);
        $this->lookups->setStatus('positions', 1, $id, 'INACTIVE');
        $rows = $this->connection->table('org_positions')->where('position_id', $id)->get()->getResultArray();
        self::assertCount(1, $rows);
        self::assertSame('Senior Store Manager', $rows[0]['position_name']);
        self::assertSame('INACTIVE', $rows[0]['status']);
        self::assertSame(3, $this->connection->table('log_audit_logs')->countAllResults());
    }

    public function testDuplicateLookupCodeIsRejected(): void
    {
        $this->lookups->save('departments', 1, ['code'=>'FINANCE','name'=>'Finance']);
        $this->expectException(DomainException::class);
        $this->lookups->save('departments', 1, ['code'=>'FINANCE','name'=>'Finance Operations']);
    }

    public function testUnknownLookupTypeAndMissingMigrationFailClosed(): void
    {
        try {
            $this->lookups->save('unknown', 1, ['code'=>'X1','name'=>'Unknown']);
            self::fail('Unknown lookup types must be rejected.');
        } catch (DomainException) {
            self::assertTrue(true);
        }
        $this->connection->query('DROP TABLE org_positions');
        $this->expectException(DomainException::class);
        $this->lookups->all();
    }
}
