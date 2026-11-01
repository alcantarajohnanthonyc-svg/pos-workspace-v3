<?php

namespace Tests\Unit;

use App\Services\AuditLogService;
use App\Services\OutletAccessService;
use App\Services\UserManagementService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use DomainException;

final class UserManagementServiceTest extends CIUnitTestCase
{
    private BaseConnection $connection;
    private UserManagementService $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = Database::connect(['DSN'=>'','hostname'=>'','username'=>'','password'=>'','database'=>':memory:','DBDriver'=>'SQLite3','DBPrefix'=>'','pConnect'=>false,'DBDebug'=>true,'charset'=>'utf8','DBCollat'=>'utf8_general_ci'], false);
        foreach ([
            'CREATE TABLE usr_roles (role_id INTEGER PRIMARY KEY,role_code TEXT,role_name TEXT,status TEXT)',
            'CREATE TABLE usr_accounts (user_id INTEGER PRIMARY KEY,username TEXT,employee_code TEXT,first_name TEXT,last_name TEXT,avatar_url TEXT,birthday TEXT,email TEXT,password_hash TEXT,role_id INTEGER,position_id INTEGER,department_id INTEGER,default_module_id INTEGER,account_status TEXT,created_at TEXT,updated_at TEXT)',
            'CREATE TABLE org_positions (position_id INTEGER PRIMARY KEY,position_name TEXT,status TEXT)',
            'CREATE TABLE org_departments (department_id INTEGER PRIMARY KEY,department_name TEXT,status TEXT)',
            'CREATE TABLE mod_modules (module_id INTEGER PRIMARY KEY,module_code TEXT,module_name TEXT,status TEXT,sort_order INTEGER)',
            'CREATE TABLE mod_module_actions (module_action_id INTEGER PRIMARY KEY,module_id INTEGER,action_code TEXT,is_enabled INTEGER,requires_company_scope INTEGER,requires_branch_scope INTEGER)',
            'CREATE TABLE rbac_role_module_actions (role_id INTEGER,module_action_id INTEGER)',
            'CREATE TABLE rbac_user_module_actions (user_id INTEGER,module_action_id INTEGER)',
            'CREATE TABLE mod_module_companies (module_id INTEGER,company_id INTEGER)',
            'CREATE TABLE mod_module_branches (module_id INTEGER,company_id INTEGER,branch_id INTEGER)',
            'CREATE TABLE rbac_user_company_scopes (user_id INTEGER,module_id INTEGER,company_id INTEGER)',
            'CREATE TABLE rbac_user_branch_scopes (user_id INTEGER,module_id INTEGER,company_id INTEGER,branch_id INTEGER)',
            'CREATE TABLE org_companies (company_id INTEGER,status TEXT)',
            'CREATE TABLE org_branches (branch_id INTEGER,company_id INTEGER,status TEXT)',
            'CREATE TABLE org_reporting_lines (employee_user_id INTEGER,reports_to_user_id INTEGER)',
            'CREATE TABLE sec_active_sessions (user_id INTEGER PRIMARY KEY,session_hash TEXT)',
            'CREATE TABLE log_audit_logs (audit_log_id INTEGER PRIMARY KEY AUTOINCREMENT,actor_user_id INTEGER,target_user_id INTEGER,module_id INTEGER,event_code TEXT,result TEXT,before_data TEXT,after_data TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
        ] as $sql) $this->connection->query($sql);

        $this->connection->table('usr_roles')->insertBatch([
            ['role_id'=>1,'role_code'=>'SUPERVISOR','role_name'=>'Supervisor','status'=>'ACTIVE'],
            ['role_id'=>2,'role_code'=>'USER','role_name'=>'User','status'=>'ACTIVE'],
            ['role_id'=>3,'role_code'=>'SUPERADMIN','role_name'=>'Super Administrator','status'=>'ACTIVE'],
        ]);
        foreach ([
            ['user_id'=>10,'email'=>'manager@example.invalid','first_name'=>'Manager','role_id'=>1,'account_status'=>'ACTIVE'],
            ['user_id'=>20,'email'=>'report@example.invalid','first_name'=>'Report','role_id'=>2,'account_status'=>'ACTIVE','password_hash'=>'must-not-be-selected'],
            ['user_id'=>30,'email'=>'admin@example.invalid','role_id'=>3,'account_status'=>'ACTIVE'],
        ] as $account) {
            $this->connection->table('usr_accounts')->insert($account + ['username'=>null,'employee_code'=>null,'last_name'=>null,'avatar_url'=>null,'birthday'=>null,'password_hash'=>null,'position_id'=>null,'default_module_id'=>null,'created_at'=>null,'updated_at'=>null]);
        }
        $this->connection->table('mod_modules')->insertBatch([
            ['module_id'=>5,'module_code'=>'EMPLOYEE_MANAGEMENT','module_name'=>'Employee Management','status'=>'ACTIVE','sort_order'=>1],
            ['module_id'=>6,'module_code'=>'INVENTORY','module_name'=>'Inventory','status'=>'ACTIVE','sort_order'=>2],
        ]);
        $this->connection->table('mod_module_actions')->insertBatch([
            ['module_action_id'=>50,'module_id'=>5,'action_code'=>'VIEW','is_enabled'=>1,'requires_company_scope'=>1,'requires_branch_scope'=>1],
            ['module_action_id'=>51,'module_id'=>5,'action_code'=>'MANAGE_ACCESS','is_enabled'=>1,'requires_company_scope'=>1,'requires_branch_scope'=>1],
            ['module_action_id'=>60,'module_id'=>6,'action_code'=>'VIEW','is_enabled'=>1,'requires_company_scope'=>1,'requires_branch_scope'=>1],
        ]);
        $this->connection->table('org_reporting_lines')->insert(['employee_user_id'=>20,'reports_to_user_id'=>10]);
        $this->connection->table('mod_module_companies')->insertBatch([['module_id'=>5,'company_id'=>100],['module_id'=>6,'company_id'=>100]]);
        $this->connection->table('mod_module_branches')->insertBatch([['module_id'=>5,'company_id'=>100,'branch_id'=>200],['module_id'=>6,'company_id'=>100,'branch_id'=>200]]);
        $this->connection->table('org_companies')->insert(['company_id'=>100,'status'=>'ACTIVE']);
        $this->connection->table('org_branches')->insert(['branch_id'=>200,'company_id'=>100,'status'=>'ACTIVE']);
        $this->connection->table('rbac_user_company_scopes')->insertBatch([
            ['user_id'=>10,'module_id'=>5,'company_id'=>100],['user_id'=>20,'module_id'=>5,'company_id'=>100],
            ['user_id'=>10,'module_id'=>6,'company_id'=>100],['user_id'=>20,'module_id'=>6,'company_id'=>100],
        ]);
        $this->connection->table('rbac_user_branch_scopes')->insertBatch([
            ['user_id'=>10,'module_id'=>5,'company_id'=>100,'branch_id'=>200],['user_id'=>20,'module_id'=>5,'company_id'=>100,'branch_id'=>200],
            ['user_id'=>10,'module_id'=>6,'company_id'=>100,'branch_id'=>200],['user_id'=>20,'module_id'=>6,'company_id'=>100,'branch_id'=>200],
        ]);
        $this->connection->table('rbac_role_module_actions')->insertBatch([['role_id'=>1,'module_action_id'=>50],['role_id'=>2,'module_action_id'=>50],['role_id'=>2,'module_action_id'=>60]]);
        $this->connection->table('rbac_user_module_actions')->insertBatch([['user_id'=>10,'module_action_id'=>50],['user_id'=>20,'module_action_id'=>51],['user_id'=>20,'module_action_id'=>60]]);
        $this->users = new UserManagementService($this->connection, new OutletAccessService($this->connection), new AuditLogService($this->connection));
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        parent::tearDown();
    }

    public function testProfileUpdateIgnoresPrivilegedIdentityFieldsAndAuditsTarget(): void
    {
        $this->users->updateOwnProfile(20, 20, ['first_name'=>'Updated','birthday'=>'2000-02-29','email'=>'attacker@example.invalid','role_id'=>3,'password_hash'=>'bad']);
        $row = $this->connection->table('usr_accounts')->where('user_id',20)->get()->getRowArray();
        self::assertSame('Updated', $row['first_name']);
        self::assertSame('report@example.invalid', $row['email']);
        self::assertSame('2', (string) $row['role_id']);
        self::assertSame('must-not-be-selected', $row['password_hash']);
        $event = $this->connection->table('log_audit_logs')->where('event_code','ACCOUNT_PROFILE_UPDATED')->get()->getRowArray();
        self::assertSame('20', (string) $event['target_user_id']);
        self::assertStringNotContainsString('password_hash', $event['before_data'] . $event['after_data']);
    }

    public function testProfileCannotBeEditedOnBehalfOfAnotherAccount(): void
    {
        $this->expectException(DomainException::class);
        $this->users->updateOwnProfile(10, 20, ['first_name'=>'Changed']);
    }

    public function testSupervisorSeesOnlyDirectReportsWithSharedActiveScope(): void
    {
        $result = $this->users->listAccounts(10);
        self::assertSame([20], array_map('intval', array_column($result['items'], 'user_id')));
        self::assertSame('report@example.invalid', $this->users->account(10,20)['email']);
        $this->connection->table('org_reporting_lines')->insert(['employee_user_id'=>30,'reports_to_user_id'=>10]);
        $this->expectException(DomainException::class);
        $this->users->account(10,30);
    }

    public function testSupervisorCannotSeeDirectReportOutsideEmployeeManagementScope(): void
    {
        $this->connection->table('rbac_user_company_scopes')->where(['user_id'=>10,'module_id'=>5])->delete();
        $this->connection->table('rbac_user_branch_scopes')->where(['user_id'=>10,'module_id'=>5])->delete();
        self::assertSame([], $this->users->listAccounts(10)['items']);
        $this->expectException(DomainException::class);
        $this->users->account(10,20);
    }

    public function testEffectiveAccessPreviewReturnsSpecificGrantAndStateReasons(): void
    {
        $preview = $this->users->effectiveAccessPreview(20,20);
        $byModule = array_column($preview, null, 'module_code');
        self::assertTrue($byModule['INVENTORY']['allowed']);
        $employeeActions = array_values(array_filter($preview, static fn (array $row): bool => $row['module_code'] === 'EMPLOYEE_MANAGEMENT'));
        $employeeByAction = array_column($employeeActions, null, 'action_code');
        self::assertContains('USER_GRANT_MISSING', $employeeByAction['VIEW']['denied_reasons']);
        self::assertContains('ROLE_GRANT_MISSING', $employeeByAction['MANAGE_ACCESS']['denied_reasons']);
    }

    public function testSupervisorPreviewOmitsModulesOutsideSharedScope(): void
    {
        $this->connection->table('rbac_user_company_scopes')->where(['user_id'=>10,'module_id'=>6])->delete();
        $this->connection->table('rbac_user_branch_scopes')->where(['user_id'=>10,'module_id'=>6])->delete();
        $preview = $this->users->effectiveAccessPreview(10,20);
        self::assertSame(['EMPLOYEE_MANAGEMENT'], array_values(array_unique(array_column($preview, 'module_code'))));
    }

    public function testAccountHistoryIsScopeAuthorizedAndFiltersSensitiveFields(): void
    {
        $this->connection->table('log_audit_logs')->insert([
            'actor_user_id'=>10,'target_user_id'=>20,'event_code'=>'ACCESS_REVIEWED','result'=>'SUCCESS',
            'before_data'=>json_encode(['role_id'=>2,'session_hash'=>'must-not-render']),
            'after_data'=>json_encode(['scope'=>['company_id'=>100],'password_hash'=>'must-not-render']),
        ]);
        $history = $this->users->accountHistory(10,20);
        self::assertCount(1, $history);
        self::assertArrayNotHasKey('session_hash', $history[0]['before_data']);
        self::assertArrayNotHasKey('password_hash', $history[0]['after_data']);
    }

    public function testSuperadminDeactivationRevokesRegisteredSessionAndAuditsTarget(): void
    {
        $this->connection->table('sec_active_sessions')->insert(['user_id'=>20,'session_hash'=>'private-session-hash']);
        $this->users->setAccountStatus(30,20,'INACTIVE');
        self::assertSame('INACTIVE', $this->connection->table('usr_accounts')->select('account_status')->where('user_id',20)->get()->getRowArray()['account_status']);
        self::assertSame(0, $this->connection->table('sec_active_sessions')->where('user_id',20)->countAllResults());
        $event = $this->connection->table('log_audit_logs')->where('event_code','ACCOUNT_STATUS_CHANGED')->get()->getRowArray();
        self::assertSame('20', (string) $event['target_user_id']);
        self::assertStringNotContainsString('private-session-hash', $event['before_data'] . $event['after_data']);
    }

    public function testLastActiveSuperadminCannotBeDeactivated(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('own account');
        $this->users->setAccountStatus(30,30,'INACTIVE');
    }

    public function testSupervisorCannotChangeAccountLifecycle(): void
    {
        $this->expectException(DomainException::class);
        $this->users->setAccountStatus(10,20,'INACTIVE');
    }

    public function testAccountActivationRequiresAnActiveRoleAndUsableModuleGrant(): void
    {
        $this->connection->table('usr_accounts')->insert(['user_id'=>40,'email'=>'unconfigured@example.invalid','role_id'=>2,'account_status'=>'PENDING']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('at least one active module action');
        $this->users->setAccountStatus(30,40,'ACTIVE');
    }

    public function testSuperadminCanAssignActivePositionAndDepartmentWithTargetAudit(): void
    {
        $this->connection->table('org_positions')->insert(['position_id'=>4,'position_name'=>'Store Manager','status'=>'ACTIVE']);
        $this->connection->table('org_departments')->insert(['department_id'=>8,'department_name'=>'Operations','status'=>'ACTIVE']);
        $this->users->updateOrganizationAssignment(30,20,4,8);
        $account=$this->connection->table('usr_accounts')->select('position_id,department_id')->where('user_id',20)->get()->getRowArray();
        self::assertSame('4',(string)$account['position_id']);
        self::assertSame('8',(string)$account['department_id']);
        self::assertSame(1,$this->connection->table('log_audit_logs')->where(['target_user_id'=>20,'event_code'=>'ACCOUNT_ORGANIZATION_ASSIGNMENT_CHANGED'])->countAllResults());
    }

    public function testSupervisorCannotAssignPositionOrDepartment(): void
    {
        $this->expectException(DomainException::class);
        $this->users->updateOrganizationAssignment(10,20,null,null);
    }
}
