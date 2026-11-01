<?php

namespace Tests\Unit;

use App\Services\OutletAccessService;
use App\Services\OutletListService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use DomainException;

final class OutletAccessServiceTest extends CIUnitTestCase
{
    private BaseConnection $testDb;
    private OutletAccessService $acl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDb=Database::connect(['DSN'=>'','hostname'=>'','username'=>'','password'=>'','database'=>':memory:','DBDriver'=>'SQLite3','DBPrefix'=>'','pConnect'=>false,'DBDebug'=>true,'charset'=>'utf8mb4','DBCollat'=>'utf8mb4_general_ci'],false);
        $schema=[
            'usr_roles'=>['role_id'=>'INTEGER','role_code'=>'TEXT','status'=>'TEXT'],
            'usr_accounts'=>['user_id'=>'INTEGER','role_id'=>'INTEGER','account_status'=>'TEXT','first_name'=>['type'=>'TEXT','null'=>true],'last_name'=>['type'=>'TEXT','null'=>true],'updated_at'=>['type'=>'TEXT','null'=>true]],
            'mod_modules'=>['module_id'=>'INTEGER','module_code'=>'TEXT','module_name'=>['type'=>'TEXT','null'=>true],'status'=>'TEXT'],
            'mod_module_actions'=>['module_action_id'=>'INTEGER','module_id'=>'INTEGER','action_code'=>'TEXT','is_enabled'=>'INTEGER','requires_approval'=>'INTEGER','approval_mode'=>'TEXT'],
            'rbac_role_module_actions'=>['role_id'=>'INTEGER','module_action_id'=>'INTEGER'],
            'rbac_user_module_actions'=>['user_id'=>'INTEGER','module_action_id'=>'INTEGER','created_by'=>['type'=>'INTEGER','null'=>true]],
            'mod_module_companies'=>['module_id'=>'INTEGER','company_id'=>'INTEGER'],
            'rbac_user_company_scopes'=>['user_id'=>'INTEGER','module_id'=>'INTEGER','company_id'=>'INTEGER','created_by'=>['type'=>'INTEGER','null'=>true]],
            'mod_module_branches'=>['module_id'=>'INTEGER','company_id'=>'INTEGER','branch_id'=>'INTEGER'],
            'rbac_user_branch_scopes'=>['user_id'=>'INTEGER','module_id'=>'INTEGER','company_id'=>'INTEGER','branch_id'=>'INTEGER','created_by'=>['type'=>'INTEGER','null'=>true]],
            'org_companies'=>['company_id'=>'INTEGER','status'=>'TEXT'],
            'org_branches'=>['branch_id'=>'INTEGER','company_id'=>'INTEGER','status'=>'TEXT'],
            'sys_mandatory_approval_policies'=>['mandatory_policy_id'=>['type'=>'INTEGER','auto_increment'=>true],'action_code'=>'TEXT','is_mandatory_global'=>'INTEGER'],
            'apr_module_approvers'=>['module_action_id'=>'INTEGER','stage_order'=>'INTEGER','approver_user_id'=>'INTEGER'],
            'mod_module_administrators'=>['module_id'=>'INTEGER','user_id'=>'INTEGER'],
            'org_reporting_lines'=>['employee_user_id'=>'INTEGER','reports_to_user_id'=>'INTEGER','created_by'=>['type'=>'INTEGER','null'=>true],'created_at'=>['type'=>'TEXT','null'=>true],'updated_at'=>['type'=>'TEXT','null'=>true]],
            'sec_active_sessions'=>['user_id'=>'INTEGER','session_hash'=>'TEXT'],
            'apr_requests'=>['approval_request_id'=>['type'=>'INTEGER','auto_increment'=>true],'request_code'=>'TEXT','module_action_id'=>'INTEGER','target_type'=>'TEXT','target_record_id'=>['type'=>'INTEGER','null'=>true],'requester_user_id'=>'INTEGER','company_id'=>['type'=>'INTEGER','null'=>true],'branch_id'=>['type'=>'INTEGER','null'=>true],'request_status'=>'TEXT','requested_data'=>'TEXT','created_at'=>['type'=>'TEXT','null'=>true],'updated_at'=>['type'=>'TEXT','null'=>true],'applied_at'=>['type'=>'TEXT','null'=>true]],
            'apr_request_stages'=>['approval_stage_id'=>['type'=>'INTEGER','auto_increment'=>true],'approval_request_id'=>'INTEGER','stage_order'=>'INTEGER','approver_user_id'=>'INTEGER','stage_status'=>'TEXT','decision_reason'=>['type'=>'TEXT','null'=>true],'decided_at'=>['type'=>'TEXT','null'=>true],'created_at'=>['type'=>'TEXT','null'=>true]],
            'log_audit_logs'=>['audit_log_id'=>['type'=>'INTEGER','auto_increment'=>true],'actor_user_id'=>'INTEGER','target_user_id'=>['type'=>'INTEGER','null'=>true],'module_id'=>['type'=>'INTEGER','null'=>true],'event_code'=>'TEXT','result'=>'TEXT','before_data'=>['type'=>'TEXT','null'=>true],'after_data'=>['type'=>'TEXT','null'=>true],'created_at'=>['type'=>'TEXT','null'=>true]],
            'out_outlets'=>['outlet_id'=>['type'=>'INTEGER','auto_increment'=>true],'company_id'=>'INTEGER','branch_id'=>'INTEGER','outlet_code'=>'TEXT','outlet_name'=>'TEXT','status'=>'TEXT','archived_at'=>['type'=>'TEXT','null'=>true],'archived_by'=>['type'=>'INTEGER','null'=>true],'created_by'=>'INTEGER','updated_by'=>['type'=>'INTEGER','null'=>true],'created_at'=>'TEXT','updated_at'=>['type'=>'TEXT','null'=>true]],
        ];
        foreach($schema as $table=>$columns) { $fields=[]; $primary=[]; $forge=Database::forge($this->testDb); foreach($columns as $name=>$type) { $definition=is_array($type)?$type:['type'=>$type]; $fields[$name]=$definition; if(!empty($definition['auto_increment'])) $primary[]=$name; } $forge->addField($fields); foreach($primary as $key) $forge->addKey($key,true); $forge->createTable($table); }
        $this->testDb->table('usr_roles')->insertBatch([['role_id'=>1,'role_code'=>'REGULAR','status'=>'ACTIVE'],['role_id'=>2,'role_code'=>'VIEWER','status'=>'ACTIVE'],['role_id'=>3,'role_code'=>'SUPERADMIN','status'=>'ACTIVE'],['role_id'=>4,'role_code'=>'REGULAR_APPROVER','status'=>'ACTIVE']]);
        $this->testDb->table('usr_accounts')->insertBatch([['user_id'=>10,'role_id'=>1,'account_status'=>'ACTIVE'],['user_id'=>11,'role_id'=>4,'account_status'=>'ACTIVE'],['user_id'=>12,'role_id'=>3,'account_status'=>'ACTIVE'],['user_id'=>13,'role_id'=>4,'account_status'=>'ACTIVE'],['user_id'=>14,'role_id'=>2,'account_status'=>'ACTIVE']]);
        $this->testDb->table('mod_modules')->insert(['module_id'=>5,'module_code'=>'OUTLET_LIST','module_name'=>'Outlet List','status'=>'ACTIVE']);
        $this->testDb->table('mod_module_actions')->insertBatch([['module_action_id'=>20,'module_id'=>5,'action_code'=>'VIEW','is_enabled'=>1,'requires_approval'=>0,'approval_mode'=>'NONE'],['module_action_id'=>21,'module_id'=>5,'action_code'=>'CREATE','is_enabled'=>1,'requires_approval'=>1,'approval_mode'=>'ADMIN'],['module_action_id'=>22,'module_id'=>5,'action_code'=>'APPROVE','is_enabled'=>1,'requires_approval'=>0,'approval_mode'=>'NONE']]);
        $this->testDb->table('rbac_role_module_actions')->insert(['role_id'=>1,'module_action_id'=>20]);
        $this->testDb->table('rbac_role_module_actions')->insert(['role_id'=>2,'module_action_id'=>21]);
        $this->testDb->table('rbac_role_module_actions')->insert(['role_id'=>4,'module_action_id'=>21]);
        $this->testDb->table('rbac_role_module_actions')->insert(['role_id'=>4,'module_action_id'=>22]);
        $this->testDb->table('rbac_user_module_actions')->insertBatch([['user_id'=>10,'module_action_id'=>20],['user_id'=>10,'module_action_id'=>21],['user_id'=>14,'module_action_id'=>21],['user_id'=>11,'module_action_id'=>21],['user_id'=>11,'module_action_id'=>22],['user_id'=>13,'module_action_id'=>22]]);
        $this->testDb->table('apr_module_approvers')->insertBatch([['module_action_id'=>21,'stage_order'=>1,'approver_user_id'=>11],['module_action_id'=>21,'stage_order'=>2,'approver_user_id'=>13]]);
        $this->testDb->table('mod_module_companies')->insert(['module_id'=>5,'company_id'=>30]);
        $this->testDb->table('rbac_user_company_scopes')->insert(['user_id'=>10,'module_id'=>5,'company_id'=>30]);
        $this->testDb->table('mod_module_branches')->insert(['module_id'=>5,'company_id'=>30,'branch_id'=>40]);
        $this->testDb->table('rbac_user_branch_scopes')->insert(['user_id'=>10,'module_id'=>5,'company_id'=>30,'branch_id'=>40]);
        $this->testDb->table('org_companies')->insert(['company_id'=>30,'status'=>'ACTIVE']);
        $this->testDb->table('org_branches')->insert(['branch_id'=>40,'company_id'=>30,'status'=>'ACTIVE']);
        $this->acl=new OutletAccessService($this->testDb);
    }

    protected function tearDown(): void { $this->testDb->close(); parent::tearDown(); }

    public function testRoleAndUserActionGrantsMustBothExist(): void
    {
        self::assertTrue($this->acl->allowed(10,'OUTLET_LIST','VIEW'));
        self::assertFalse($this->acl->allowed(10,'OUTLET_LIST','CREATE'));
        self::assertFalse($this->acl->allowed(14,'OUTLET_LIST','CREATE'));
    }

    public function testViewerCannotMutateAndInactiveModuleDeniesAccess(): void
    {
        self::assertFalse($this->acl->allowed(14,'OUTLET_LIST','CREATE'));
        $this->testDb->table('mod_modules')->where('module_id',5)->update(['status'=>'INACTIVE']);
        self::assertFalse($this->acl->allowed(10,'OUTLET_LIST','VIEW'));
    }

    public function testScopeRequiresModuleAndUserCompanyBranchIntersection(): void
    {
        self::assertTrue($this->acl->inScope(10,5,30,40));
        self::assertFalse($this->acl->inScope(10,5,30,41));
        self::assertTrue($this->acl->inScope(12,5,999,999));
    }

    public function testApprovalStagesApplyOnlyAfterSequentialApprovals(): void
    {
        $action=$this->acl->action(5,'CREATE');
        $requestId=$this->acl->submit(10,5,$action,['company_id'=>30,'branch_id'=>40,'outlet_code'=>'T-1','outlet_name'=>'Fixture','status'=>'ACTIVE'],null,null,null);
        self::assertNotNull($requestId);
        self::assertSame('WAITING',$this->testDb->table('apr_request_stages')->where(['approval_request_id'=>$requestId,'stage_order'=>2])->get()->getRowArray()['stage_status']);
        self::assertSame(0,$this->testDb->table('out_outlets')->countAllResults());
        $this->acl->decide(11,$requestId,'APPROVED','');
        self::assertSame('PENDING',$this->testDb->table('apr_request_stages')->where(['approval_request_id'=>$requestId,'stage_order'=>2])->get()->getRowArray()['stage_status']);
        self::assertSame(0,$this->testDb->table('out_outlets')->countAllResults());
        $this->acl->decide(13,$requestId,'APPROVED','');
        self::assertSame('APPLIED',$this->testDb->table('apr_requests')->where('approval_request_id',$requestId)->get()->getRowArray()['request_status']);
        self::assertSame(1,$this->testDb->table('out_outlets')->countAllResults());
    }

    public function testRejectedApprovalDoesNotApplyChange(): void
    {
        $requestId=$this->acl->submit(10,5,$this->acl->action(5,'CREATE'),['company_id'=>30,'branch_id'=>40,'outlet_code'=>'T-2','outlet_name'=>'Fixture','status'=>'ACTIVE'],null,null,null);
        $this->acl->decide(11,$requestId,'REJECTED','Not approved');
        self::assertSame('REJECTED',$this->testDb->table('apr_requests')->where('approval_request_id',$requestId)->get()->getRowArray()['request_status']);
        self::assertSame(0,$this->testDb->table('out_outlets')->countAllResults());
    }

    public function testMissingOrSelfApproverFailsClosed(): void
    {
        $this->testDb->table('apr_module_approvers')->where('module_action_id',21)->delete();
        $this->testDb->table('apr_module_approvers')->insert(['module_action_id'=>21,'stage_order'=>1,'approver_user_id'=>10]);
        try {
            $this->acl->submit(10,5,$this->acl->action(5,'CREATE'),['company_id'=>30,'branch_id'=>40,'outlet_code'=>'T-3','outlet_name'=>'Fixture','status'=>'ACTIVE'],null,null,null);
            self::fail('Expected missing eligible approver to block the request.');
        } catch (\DomainException $e) {
            self::assertSame('This action cannot proceed because no approver is assigned. Please contact your administrator.',$e->getMessage());
        }
    }

    public function testArchiveAndRestorePreserveOutletRow(): void
    {
        $this->testDb->table('out_outlets')->insert(['outlet_id'=>90,'company_id'=>30,'branch_id'=>40,'outlet_code'=>'T-4','outlet_name'=>'Fixture','status'=>'ACTIVE','archived_at'=>null,'archived_by'=>null,'created_by'=>10,'updated_by'=>null,'created_at'=>'2026-10-23']);
        $this->acl->apply(10,5,'DELETE',[],90);
        self::assertNotNull($this->testDb->table('out_outlets')->where('outlet_id',90)->get()->getRowArray()['archived_at']);
        $this->acl->apply(10,5,'RESTORE',[],90);
        self::assertNull($this->testDb->table('out_outlets')->where('outlet_id',90)->get()->getRowArray()['archived_at']);
        self::assertSame(1,$this->testDb->table('out_outlets')->countAllResults());
    }

    public function testSuperadminCanCreateOutletThroughValidatedService(): void
    {
        $service=new OutletListService($this->testDb,$this->acl);
        self::assertNull($service->save(12,null,['company_id'=>30,'branch_id'=>40,'outlet_code'=>'UNIT-1','outlet_name'=>'Unit Outlet','status'=>'ACTIVE']));
        $row=$this->testDb->table('out_outlets')->where('outlet_code','UNIT-1')->get()->getRowArray();
        self::assertSame('Unit Outlet',$row['outlet_name']);
        self::assertSame(12,(int)$row['created_by']);
    }

    public function testViewerCannotCreateOutletByCallingServiceDirectly(): void
    {
        $service=new OutletListService($this->testDb,$this->acl);
        $this->expectException(DomainException::class);
        $service->save(14,null,['company_id'=>30,'branch_id'=>40,'outlet_code'=>'UNIT-2','outlet_name'=>'Unit Outlet','status'=>'ACTIVE']);
    }

    public function testEmployeeAccessChangeStaysPendingUntilApproval(): void
    {
        $this->testDb->table('mod_modules')->insert(['module_id'=>6,'module_code'=>'EMPLOYEE_MANAGEMENT','status'=>'ACTIVE']);
        $this->testDb->table('mod_module_actions')->insertBatch([['module_action_id'=>30,'module_id'=>6,'action_code'=>'MANAGE_ACCESS','is_enabled'=>1,'requires_approval'=>1,'approval_mode'=>'ADMIN'],['module_action_id'=>31,'module_id'=>6,'action_code'=>'APPROVE','is_enabled'=>1,'requires_approval'=>0,'approval_mode'=>'NONE']]);
        $this->testDb->table('rbac_role_module_actions')->insertBatch([['role_id'=>1,'module_action_id'=>30],['role_id'=>4,'module_action_id'=>31]]);
        $this->testDb->table('rbac_user_module_actions')->insertBatch([['user_id'=>10,'module_action_id'=>30],['user_id'=>11,'module_action_id'=>31]]);
        $this->testDb->table('apr_module_approvers')->insert(['module_action_id'=>30,'stage_order'=>1,'approver_user_id'=>11]);
        $this->testDb->table('rbac_user_company_scopes')->insert(['user_id'=>11,'module_id'=>5,'company_id'=>30]);
        $this->testDb->table('rbac_user_branch_scopes')->insert(['user_id'=>11,'module_id'=>5,'company_id'=>30,'branch_id'=>40]);
        $this->testDb->table('org_reporting_lines')->insert(['employee_user_id'=>14,'reports_to_user_id'=>10]);
        $this->testDb->table('rbac_user_company_scopes')->insert(['user_id'=>14,'module_id'=>5,'company_id'=>999]);
        $action=$this->acl->action(6,'MANAGE_ACCESS');
        $requestId=$this->acl->submit(10,6,$action,['target_module_id'=>5,'employee_module_id'=>6,'actions'=>['VIEW'],'employee_actions'=>[],'companies'=>[30],'branches'=>[40]],14,null,null);
        self::assertNotNull($requestId);
        try {
            $this->acl->submit(10,6,$action,['target_module_id'=>5,'employee_module_id'=>6,'actions'=>[],'employee_actions'=>[],'companies'=>[30],'branches'=>[40]],14,null,null);
            self::fail('A second pending change for one account should be blocked.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('already has a pending access change',$exception->getMessage());
        }
        $assigned=$this->acl->pendingFor(11);
        self::assertCount(1,$assigned);
        self::assertSame($requestId,$assigned[0]['request_id']);
        self::assertSame('MODULE_ACCESS',$assigned[0]['summary']['change_type']);
        self::assertSame(0,$this->testDb->table('rbac_user_module_actions')->where(['user_id'=>14,'module_action_id'=>20])->countAllResults());
        $this->acl->decide(11,$requestId,'APPROVED','');
        self::assertSame(1,$this->testDb->table('rbac_user_module_actions')->where(['user_id'=>14,'module_action_id'=>20])->countAllResults());
        self::assertSame(1,$this->testDb->table('rbac_user_module_actions')->where(['user_id'=>14,'module_action_id'=>21])->countAllResults());
        self::assertSame(1,$this->testDb->table('rbac_user_company_scopes')->where(['user_id'=>14,'module_id'=>5,'company_id'=>999])->countAllResults());
    }

    public function testInvalidPendingUserChangeIsVisibleForRejectionButCannotBeApproved(): void
    {
        $this->testDb->table('mod_modules')->insert(['module_id'=>6,'module_code'=>'EMPLOYEE_MANAGEMENT','status'=>'ACTIVE']);
        $this->testDb->table('mod_module_actions')->insertBatch([['module_action_id'=>30,'module_id'=>6,'action_code'=>'MANAGE_ACCESS','is_enabled'=>1,'requires_approval'=>1,'approval_mode'=>'ADMIN'],['module_action_id'=>31,'module_id'=>6,'action_code'=>'APPROVE','is_enabled'=>1,'requires_approval'=>0,'approval_mode'=>'NONE']]);
        $this->testDb->table('rbac_role_module_actions')->insertBatch([['role_id'=>1,'module_action_id'=>30],['role_id'=>4,'module_action_id'=>31]]);
        $this->testDb->table('rbac_user_module_actions')->insertBatch([['user_id'=>10,'module_action_id'=>30],['user_id'=>11,'module_action_id'=>31]]);
        $this->testDb->table('apr_module_approvers')->insert(['module_action_id'=>30,'stage_order'=>1,'approver_user_id'=>11]);
        $this->testDb->table('org_reporting_lines')->insert(['employee_user_id'=>14,'reports_to_user_id'=>10]);
        $this->testDb->table('mod_module_companies')->insert(['module_id'=>6,'company_id'=>30]);
        $this->testDb->table('mod_module_branches')->insert(['module_id'=>6,'company_id'=>30,'branch_id'=>40]);
        foreach([10,11,14] as $scopedUser){$this->testDb->table('rbac_user_company_scopes')->insert(['user_id'=>$scopedUser,'module_id'=>6,'company_id'=>30]);$this->testDb->table('rbac_user_branch_scopes')->insert(['user_id'=>$scopedUser,'module_id'=>6,'company_id'=>30,'branch_id'=>40]);}
        $requestId=$this->acl->submit(10,6,$this->acl->action(6,'MANAGE_ACCESS'),['change_type'=>'ROLE_ASSIGNMENT','target_module_id'=>6,'role_id'=>1],14,null,null);
        $this->testDb->table('usr_roles')->where('role_id',1)->update(['status'=>'INACTIVE']);
        $queue=$this->acl->pendingFor(11);
        self::assertCount(1,$queue);
        self::assertFalse($queue[0]['can_approve']);
        self::assertStringContainsString('no longer valid',$queue[0]['validation_error']);
        $this->acl->decide(11,$requestId,'REJECTED','The requested role is no longer active.');
        self::assertSame('REJECTED',$this->testDb->table('apr_requests')->where('approval_request_id',$requestId)->get()->getRowArray()['request_status']);
        self::assertSame(2,(int)$this->testDb->table('usr_accounts')->select('role_id')->where('user_id',14)->get()->getRowArray()['role_id']);
    }

    public function testCombinedUsesDirectManagerThenDeduplicatedModuleAdministrators(): void
    {
        $this->testDb->table('mod_module_actions')->where('module_action_id',21)->update(['approval_mode'=>'COMBINED']);
        $this->testDb->table('org_reporting_lines')->insert(['employee_user_id'=>10,'reports_to_user_id'=>11]);
        $this->testDb->table('mod_module_administrators')->insertBatch([
            ['module_id'=>5,'user_id'=>11], ['module_id'=>5,'user_id'=>13],
        ]);
        $requestId=$this->acl->submit(10,5,$this->acl->action(5,'CREATE'),['company_id'=>30,'branch_id'=>40,'outlet_code'=>'T-C','outlet_name'=>'Combined','status'=>'ACTIVE'],null,null,null);
        $stages=$this->testDb->table('apr_request_stages')->where('approval_request_id',$requestId)->orderBy('stage_order')->get()->getResultArray();
        self::assertSame([11,13],array_map('intval',array_column($stages,'approver_user_id')));
        $this->acl->decide(11,$requestId,'APPROVED','');
        self::assertSame('PENDING',$this->testDb->table('apr_request_stages')->where(['approval_request_id'=>$requestId,'stage_order'=>2])->get()->getRowArray()['stage_status']);
        $this->acl->decide(13,$requestId,'APPROVED','');
        self::assertSame('APPLIED',$this->testDb->table('apr_requests')->where('approval_request_id',$requestId)->get()->getRowArray()['request_status']);
    }

    public function testHierarchyUsesDirectManagerBeforeDeduplicatedActionApprovers(): void
    {
        $this->testDb->table('mod_module_actions')->where('module_action_id',21)->update(['approval_mode'=>'HIERARCHY']);
        $this->testDb->table('org_reporting_lines')->insert(['employee_user_id'=>10,'reports_to_user_id'=>11]);

        $requestId=$this->acl->submit(10,5,$this->acl->action(5,'CREATE'),[
            'company_id'=>30,'branch_id'=>40,'outlet_code'=>'T-H','outlet_name'=>'Hierarchy','status'=>'ACTIVE',
        ],null,null,null);

        $stages=$this->testDb->table('apr_request_stages')->where('approval_request_id',$requestId)->orderBy('stage_order')->get()->getResultArray();
        self::assertSame([11,13],array_map('intval',array_column($stages,'approver_user_id')));
        self::assertSame(['PENDING','WAITING'],array_column($stages,'stage_status'));
    }

    public function testAccessReductionRevokesTheTargetRegisteredSession(): void
    {
        $this->testDb->table('rbac_user_module_actions')->insert(['user_id'=>14,'module_action_id'=>20]);
        $this->testDb->table('rbac_user_company_scopes')->insert(['user_id'=>14,'module_id'=>5,'company_id'=>30]);
        $this->testDb->table('rbac_user_branch_scopes')->insert(['user_id'=>14,'module_id'=>5,'company_id'=>30,'branch_id'=>40]);
        $this->testDb->table('sec_active_sessions')->insert(['user_id'=>14,'session_hash'=>'fixture-hash']);
        $this->acl->applyUserAccess(10,14,5,['actions'=>[],'companies'=>[],'branches'=>[]]);
        self::assertSame(0,$this->testDb->table('sec_active_sessions')->where('user_id',14)->countAllResults());
    }

    public function testRoleAssignmentRemainsPendingUntilApprovalsComplete(): void
    {
        $this->testDb->table('mod_modules')->insert(['module_id'=>6,'module_code'=>'EMPLOYEE_MANAGEMENT','status'=>'ACTIVE']);
        $this->testDb->table('mod_module_actions')->insertBatch([['module_action_id'=>30,'module_id'=>6,'action_code'=>'MANAGE_ACCESS','is_enabled'=>1,'requires_approval'=>1,'approval_mode'=>'ADMIN'],['module_action_id'=>31,'module_id'=>6,'action_code'=>'APPROVE','is_enabled'=>1,'requires_approval'=>0,'approval_mode'=>'NONE']]);
        $this->testDb->table('rbac_role_module_actions')->insertBatch([['role_id'=>1,'module_action_id'=>30],['role_id'=>4,'module_action_id'=>31],['role_id'=>5,'module_action_id'=>20]]);
        $this->testDb->table('rbac_user_module_actions')->insertBatch([['user_id'=>10,'module_action_id'=>30],['user_id'=>11,'module_action_id'=>31],['user_id'=>10,'module_action_id'=>20]]);
        $this->testDb->table('usr_roles')->insert(['role_id'=>5,'role_code'=>'LIMITED_MANAGER','status'=>'ACTIVE']);
        $this->testDb->table('apr_module_approvers')->insert(['module_action_id'=>30,'stage_order'=>1,'approver_user_id'=>11]);
        $this->testDb->table('org_reporting_lines')->insert(['employee_user_id'=>14,'reports_to_user_id'=>10]);
        $this->testDb->table('mod_module_companies')->insert(['module_id'=>6,'company_id'=>30]);
        $this->testDb->table('mod_module_branches')->insert(['module_id'=>6,'company_id'=>30,'branch_id'=>40]);
        foreach([10,11,14] as $scopedUser){$this->testDb->table('rbac_user_company_scopes')->insert(['user_id'=>$scopedUser,'module_id'=>6,'company_id'=>30]);$this->testDb->table('rbac_user_branch_scopes')->insert(['user_id'=>$scopedUser,'module_id'=>6,'company_id'=>30,'branch_id'=>40]);}
        $this->testDb->table('sec_active_sessions')->insert(['user_id'=>14,'session_hash'=>'private-fixture-hash']);
        $this->testDb->table('mod_module_companies')->insert(['module_id'=>5,'company_id'=>31]);
        $this->testDb->table('org_companies')->insert(['company_id'=>31,'status'=>'ACTIVE']);
        $this->testDb->table('rbac_user_company_scopes')->insert(['user_id'=>14,'module_id'=>5,'company_id'=>31]);
        try {
            $this->acl->submit(10,6,$this->acl->action(6,'MANAGE_ACCESS'),['change_type'=>'ROLE_ASSIGNMENT','target_module_id'=>6,'role_id'=>5],14,null,null);
            self::fail('A Supervisor must not assign a role that activates a company scope outside their authority.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('company or branch scope',$exception->getMessage());
        }
        $this->testDb->table('rbac_user_company_scopes')->where(['user_id'=>14,'module_id'=>5,'company_id'=>31])->delete();
        $requestId=$this->acl->submit(10,6,$this->acl->action(6,'MANAGE_ACCESS'),['change_type'=>'ROLE_ASSIGNMENT','target_module_id'=>6,'role_id'=>5,'password'=>'must-not-be-persisted'],14,null,null);
        self::assertNotNull($requestId);
        self::assertSame(2,(int)$this->testDb->table('usr_accounts')->where('user_id',14)->get()->getRowArray()['role_id']);
        $payload=$this->testDb->table('apr_requests')->where('approval_request_id',$requestId)->get()->getRowArray()['requested_data'];
        self::assertStringNotContainsString('must-not-be-persisted',$payload);
        $this->acl->decide(11,$requestId,'APPROVED','');
        self::assertSame(5,(int)$this->testDb->table('usr_accounts')->where('user_id',14)->get()->getRowArray()['role_id']);
        self::assertSame(0,$this->testDb->table('sec_active_sessions')->where('user_id',14)->countAllResults());
        self::assertSame(11,(int)$this->testDb->table('log_audit_logs')->where('event_code','ACCOUNT_ROLE_ASSIGNED')->get()->getRowArray()['actor_user_id']);
        self::assertSame(14,(int)$this->testDb->table('log_audit_logs')->where('event_code','ACCOUNT_ROLE_ASSIGNED')->get()->getRowArray()['target_user_id']);

        $rejected=$this->acl->submit(10,6,$this->acl->action(6,'MANAGE_ACCESS'),['change_type'=>'ROLE_ASSIGNMENT','target_module_id'=>6,'role_id'=>1],14,null,null);
        $this->acl->decide(11,$rejected,'REJECTED','Rejected for test coverage');
        self::assertSame(5,(int)$this->testDb->table('usr_accounts')->where('user_id',14)->get()->getRowArray()['role_id']);
    }

    public function testReportingLineChangesRejectCyclesAndApplyOnlyAfterApproval(): void
    {
        $this->testDb->table('mod_modules')->insert(['module_id'=>6,'module_code'=>'EMPLOYEE_MANAGEMENT','status'=>'ACTIVE']);
        $this->testDb->table('mod_module_actions')->insertBatch([['module_action_id'=>30,'module_id'=>6,'action_code'=>'MANAGE_ACCESS','is_enabled'=>1,'requires_approval'=>1,'approval_mode'=>'ADMIN'],['module_action_id'=>31,'module_id'=>6,'action_code'=>'APPROVE','is_enabled'=>1,'requires_approval'=>0,'approval_mode'=>'NONE']]);
        $this->testDb->table('rbac_role_module_actions')->insertBatch([['role_id'=>1,'module_action_id'=>30],['role_id'=>4,'module_action_id'=>31]]);
        $this->testDb->table('rbac_user_module_actions')->insertBatch([['user_id'=>10,'module_action_id'=>30],['user_id'=>11,'module_action_id'=>31]]);
        $this->testDb->table('apr_module_approvers')->insert(['module_action_id'=>30,'stage_order'=>1,'approver_user_id'=>11]);
        $this->testDb->table('org_reporting_lines')->insertBatch([['employee_user_id'=>14,'reports_to_user_id'=>10],['employee_user_id'=>13,'reports_to_user_id'=>10]]);
        $this->testDb->table('mod_module_companies')->insert(['module_id'=>6,'company_id'=>30]);
        $this->testDb->table('mod_module_branches')->insert(['module_id'=>6,'company_id'=>30,'branch_id'=>40]);
        foreach([10,11,13,14] as $scopedUser){$this->testDb->table('rbac_user_company_scopes')->insert(['user_id'=>$scopedUser,'module_id'=>6,'company_id'=>30]);$this->testDb->table('rbac_user_branch_scopes')->insert(['user_id'=>$scopedUser,'module_id'=>6,'company_id'=>30,'branch_id'=>40]);}
        $requestId=$this->acl->submit(10,6,$this->acl->action(6,'MANAGE_ACCESS'),['change_type'=>'REPORTING_LINE','target_module_id'=>6,'reports_to_user_id'=>13],14,null,null);
        self::assertSame(10,(int)$this->testDb->table('org_reporting_lines')->where('employee_user_id',14)->get()->getRowArray()['reports_to_user_id']);
        $this->acl->decide(11,$requestId,'APPROVED','');
        self::assertSame(13,(int)$this->testDb->table('org_reporting_lines')->where('employee_user_id',14)->get()->getRowArray()['reports_to_user_id']);

        $this->testDb->table('org_reporting_lines')->where('employee_user_id',14)->update(['reports_to_user_id'=>10]);
        $this->testDb->table('org_reporting_lines')->insert(['employee_user_id'=>10,'reports_to_user_id'=>14]);
        try {
            $this->acl->submit(10,6,$this->acl->action(6,'MANAGE_ACCESS'),['change_type'=>'REPORTING_LINE','target_module_id'=>6,'reports_to_user_id'=>13],14,null,null);
            self::fail('A reporting cycle must be rejected.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('cycle',$exception->getMessage());
        }
    }
}
