<?php

namespace Tests\Unit;

use App\Services\SuperadminDashboardService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

final class SuperadminDashboardServiceTest extends CIUnitTestCase
{
    private BaseConnection $connection;
    private SuperadminDashboardService $dashboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = \Config\Database::connect([
            'DSN' => '', 'hostname' => '', 'username' => '', 'password' => '',
            'database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => '',
            'pConnect' => false, 'DBDebug' => true, 'charset' => 'utf8',
            'DBCollat' => 'utf8_general_ci', 'foreignKeys' => true,
        ]);
        $this->createSchema();
        $this->dashboard = new SuperadminDashboardService($this->connection);
    }

    public function testSnapshotUsesCurrentDatabaseCountsAndOnlyActorsAssignedApprovals(): void
    {
        $this->connection->table('usr_accounts')->insertBatch([
            ['user_id' => 1, 'account_status' => 'ACTIVE'],
            ['user_id' => 2, 'account_status' => 'PENDING'],
            ['user_id' => 3, 'account_status' => 'INACTIVE'],
        ]);
        $this->connection->table('mod_modules')->insertBatch([
            ['module_id' => 1, 'status' => 'ACTIVE'],
            ['module_id' => 2, 'status' => 'DRAFT'],
        ]);
        $this->connection->table('apr_requests')->insertBatch([
            ['approval_request_id' => 10, 'request_status' => 'PENDING'],
            ['approval_request_id' => 11, 'request_status' => 'PENDING'],
            ['approval_request_id' => 12, 'request_status' => 'APPROVED'],
        ]);
        $this->connection->table('apr_request_stages')->insertBatch([
            ['approval_stage_id' => 1, 'approval_request_id' => 10, 'approver_user_id' => 1, 'stage_status' => 'PENDING'],
            ['approval_stage_id' => 2, 'approval_request_id' => 11, 'approver_user_id' => 2, 'stage_status' => 'PENDING'],
            ['approval_stage_id' => 3, 'approval_request_id' => 12, 'approver_user_id' => 1, 'stage_status' => 'PENDING'],
        ]);

        $this->assertSame([
            'accountsTotal' => 3,
            'activeAccounts' => 1,
            'pendingAccounts' => 1,
            'activeModules' => 1,
            'assignedApprovals' => 1,
        ], $this->dashboard->snapshot(1));
    }

    public function testSnapshotRejectsInvalidActorId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->dashboard->snapshot(0);
    }

    private function createSchema(): void
    {
        $tables = [
            'CREATE TABLE IF NOT EXISTS usr_accounts (user_id INTEGER PRIMARY KEY, account_status TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS mod_modules (module_id INTEGER PRIMARY KEY, status TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS apr_requests (approval_request_id INTEGER PRIMARY KEY, request_status TEXT NOT NULL)',
            'CREATE TABLE IF NOT EXISTS apr_request_stages (approval_stage_id INTEGER PRIMARY KEY, approval_request_id INTEGER NOT NULL, approver_user_id INTEGER NOT NULL, stage_status TEXT NOT NULL)',
        ];
        foreach ($tables as $sql) {
            $this->connection->query($sql);
        }
    }
}
