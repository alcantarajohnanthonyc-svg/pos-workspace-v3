<?php

namespace Tests\Unit;

use App\Services\AuditLogService;
use App\Services\OrganizationManagementService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use DomainException;

final class OrganizationManagementServiceTest extends CIUnitTestCase
{
    private BaseConnection $connection;
    private OrganizationManagementService $organizations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection=Database::connect(['DSN'=>'','hostname'=>'','username'=>'','password'=>'','database'=>':memory:','DBDriver'=>'SQLite3','DBPrefix'=>'','pConnect'=>false,'DBDebug'=>true,'charset'=>'utf8','DBCollat'=>'utf8_general_ci'],false);
        foreach ([
            'CREATE TABLE org_companies (company_id INTEGER PRIMARY KEY AUTOINCREMENT,company_code TEXT NOT NULL UNIQUE,company_name TEXT NOT NULL,status TEXT NOT NULL,created_at TEXT,updated_at TEXT)',
            'CREATE TABLE org_branches (branch_id INTEGER PRIMARY KEY AUTOINCREMENT,company_id INTEGER NOT NULL,branch_code TEXT NOT NULL,branch_name TEXT NOT NULL,status TEXT NOT NULL,created_at TEXT,updated_at TEXT,UNIQUE(company_id,branch_code))',
            'CREATE TABLE log_audit_logs (audit_log_id INTEGER PRIMARY KEY AUTOINCREMENT,actor_user_id INTEGER,target_user_id INTEGER,module_id INTEGER,event_code TEXT,result TEXT,before_data TEXT,after_data TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
        ] as $sql) $this->connection->query($sql);
        $this->organizations=new OrganizationManagementService($this->connection,new AuditLogService($this->connection));
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();
    }

    public function testCreatesCompanyAndBranchAndAuditsBoth(): void
    {
        $companyId=$this->organizations->saveCompany(7,['code'=>'north_1','name'=>'North Company']);
        $branchId=$this->organizations->saveBranch(7,['company_id'=>$companyId,'code'=>'branch_1','name'=>'North Branch']);
        self::assertSame('NORTH_1',$this->connection->table('org_companies')->where('company_id',$companyId)->get()->getRowArray()['company_code']);
        self::assertSame($companyId,(int)$this->connection->table('org_branches')->where('branch_id',$branchId)->get()->getRowArray()['company_id']);
        self::assertSame(2,$this->connection->table('log_audit_logs')->countAllResults());
    }

    public function testDuplicateCompanyCodeIsRejected(): void
    {
        $this->organizations->saveCompany(7,['code'=>'north','name'=>'North']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already in use');
        $this->organizations->saveCompany(7,['code'=>'NORTH','name'=>'Duplicate']);
    }

    public function testBranchRequiresActiveCompany(): void
    {
        $companyId=$this->organizations->saveCompany(7,['code'=>'north','name'=>'North']);
        $this->organizations->setStatus(7,'company',$companyId,'INACTIVE');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('active company');
        $this->organizations->saveBranch(7,['company_id'=>$companyId,'code'=>'b1','name'=>'Branch']);
    }

    public function testBranchCannotBeMovedToAnotherCompany(): void
    {
        $companyA=$this->organizations->saveCompany(7,['code'=>'north','name'=>'North']);
        $companyB=$this->organizations->saveCompany(7,['code'=>'south','name'=>'South']);
        $branchId=$this->organizations->saveBranch(7,['company_id'=>$companyA,'code'=>'b1','name'=>'Branch']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cannot be moved');
        $this->organizations->saveBranch(7,['company_id'=>$companyB,'code'=>'b1','name'=>'Branch'], $branchId);
    }

    public function testRetiringCompanyPreservesBranchAndPreventsBranchReactivation(): void
    {
        $companyId=$this->organizations->saveCompany(7,['code'=>'north','name'=>'North']);
        $branchId=$this->organizations->saveBranch(7,['company_id'=>$companyId,'code'=>'b1','name'=>'Branch']);
        $this->organizations->setStatus(7,'company',$companyId,'INACTIVE');
        self::assertSame(1,$this->connection->table('org_branches')->where('branch_id',$branchId)->countAllResults());
        $this->organizations->setStatus(7,'branch',$branchId,'INACTIVE');
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Activate the parent company');
        $this->organizations->setStatus(7,'branch',$branchId,'ACTIVE');
    }
}
