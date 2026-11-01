<?php

namespace Tests\Unit;

use App\Services\ModuleRegistryService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;
use DomainException;

final class ModuleRegistryServiceTest extends CIUnitTestCase
{
    private BaseConnection $testDb;
    private ModuleRegistryService $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDb = Database::connect([
            'DSN' => '', 'hostname' => '', 'username' => '', 'password' => '',
            'database' => ':memory:', 'DBDriver' => 'SQLite3', 'DBPrefix' => '',
            'pConnect' => false, 'DBDebug' => true, 'charset' => 'utf8mb4',
            'DBCollat' => 'utf8mb4_general_ci', 'foreignKeys' => true,
        ], false);

        $forge = Database::forge($this->testDb);
        $forge->addField(['user_id' => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true], 'role_id' => ['type' => 'INTEGER', 'null' => true], 'account_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true]]);
        $forge->addKey('user_id', true);
        $forge->createTable('usr_accounts');
        $forge->addField(['role_id' => ['type' => 'INTEGER', 'auto_increment' => true], 'status' => ['type' => 'VARCHAR', 'constraint' => 10]]);
        $forge->addKey('role_id', true);
        $forge->createTable('usr_roles');
        $forge->addField([
            'module_id' => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'parent_module_id' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'module_code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'module_name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'short_name' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'module_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'route_path' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'sort_order' => ['type' => 'INTEGER', 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 10],
            'created_by' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('module_id', true);
        $forge->addUniqueKey('module_code');
        $forge->createTable('mod_modules');
        $forge->addField([
            'audit_log_id' => ['type' => 'INTEGER', 'unsigned' => true, 'auto_increment' => true],
            'actor_user_id' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'module_id' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'event_code' => ['type' => 'VARCHAR', 'constraint' => 80],
            'result' => ['type' => 'VARCHAR', 'constraint' => 10],
            'before_data' => ['type' => 'TEXT', 'null' => true],
            'after_data' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $forge->addKey('audit_log_id', true);
        $forge->createTable('log_audit_logs');
        $forge->addField(['module_id' => ['type' => 'INTEGER'], 'audience_code' => ['type' => 'VARCHAR', 'constraint' => 20], 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $forge->createTable('mod_module_audiences');
        $forge->addField(['module_id' => ['type' => 'INTEGER'], 'user_id' => ['type' => 'INTEGER'], 'assigned_by' => ['type' => 'INTEGER', 'null' => true], 'created_at' => ['type' => 'DATETIME', 'null' => true]]);
        $forge->createTable('mod_module_administrators');
        $forge->addField(['company_id'=>['type'=>'INTEGER'],'company_code'=>['type'=>'VARCHAR','constraint'=>50],'company_name'=>['type'=>'VARCHAR','constraint'=>150],'status'=>['type'=>'VARCHAR','constraint'=>10]]);
        $forge->addKey('company_id',true);
        $forge->createTable('org_companies');
        $forge->addField(['branch_id'=>['type'=>'INTEGER'],'company_id'=>['type'=>'INTEGER'],'branch_code'=>['type'=>'VARCHAR','constraint'=>50],'branch_name'=>['type'=>'VARCHAR','constraint'=>150],'status'=>['type'=>'VARCHAR','constraint'=>10]]);
        $forge->addKey('branch_id',true);
        $forge->createTable('org_branches');
        $forge->addField(['module_id'=>['type'=>'INTEGER'],'company_id'=>['type'=>'INTEGER'],'created_at'=>['type'=>'DATETIME','null'=>true]]);
        $forge->createTable('mod_module_companies');
        $forge->addField(['module_id'=>['type'=>'INTEGER'],'company_id'=>['type'=>'INTEGER'],'branch_id'=>['type'=>'INTEGER'],'created_at'=>['type'=>'DATETIME','null'=>true]]);
        $forge->createTable('mod_module_branches');
        $forge->addField(['module_action_id' => ['type' => 'INTEGER', 'auto_increment' => true], 'module_id' => ['type' => 'INTEGER'], 'action_code' => ['type' => 'VARCHAR', 'constraint' => 50], 'action_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true], 'action_description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true], 'handler_key' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true], 'is_enabled' => ['type' => 'INTEGER', 'default' => 0], 'requires_approval' => ['type' => 'INTEGER', 'default' => 0], 'approval_mode' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'NONE']]);
        $forge->addKey('module_action_id', true);
        $forge->addUniqueKey(['module_id', 'action_code']);
        $forge->createTable('mod_module_actions');
        $forge->addField(['user_id' => ['type' => 'INTEGER'], 'module_action_id' => ['type' => 'INTEGER']]);
        $forge->createTable('rbac_user_module_actions');
        $this->testDb->table('usr_roles')->insert(['role_id' => 1, 'status' => 'ACTIVE']);
        $this->testDb->table('usr_accounts')->insert(['user_id' => 7, 'role_id' => 1, 'account_status' => 'ACTIVE']);
        $this->registry = new ModuleRegistryService($this->testDb);
    }

    protected function tearDown(): void
    {
        $this->testDb->close();
        parent::tearDown();
    }

    public function testNewModuleStartsAsDraftAndIsAuditedAtomically(): void
    {
        $moduleId = $this->registry->create($this->input('MODULE_REGISTRY'), 7);

        $this->assertSame('DRAFT', $this->registry->find($moduleId)['status']);
        $audit = $this->testDb->table('log_audit_logs')->get()->getRowArray();
        $this->assertSame('MODULE_CREATED', $audit['event_code']);
        $this->assertSame('SUCCESS', $audit['result']);
        $this->assertSame($moduleId, (int) $audit['module_id']);
    }

    public function testCircularParentChangeIsRejected(): void
    {
        $parentId = $this->registry->create($this->input('MODULE_PARENT'), 7);
        $childInput = $this->input('MODULE_CHILD');
        $childInput['parent_module_id'] = (string) $parentId;
        $childId = $this->registry->create($childInput, 7);

        $parentInput = $this->input('MODULE_PARENT');
        $parentInput['parent_module_id'] = (string) $childId;
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('circular hierarchy');
        $this->registry->update($parentId, $parentInput, 7);
    }

    public function testUnsupportedModuleCannotBeActivated(): void
    {
        $moduleId = $this->registry->create($this->input('INVENTORY'), 7);

        try {
            $this->registry->changeStatus($moduleId, 'ACTIVE', 7);
            $this->fail('An unsupported module route must be rejected.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('developer-maintained route', $exception->getMessage());
        }

        $this->assertSame('DRAFT', $this->registry->find($moduleId)['status']);
    }

    public function testStaticRegistryModuleCanBeActivated(): void
    {
        $moduleId = $this->registry->create($this->input('MODULE_REGISTRY'), 7);
        $this->registry->changeStatus($moduleId, 'ACTIVE', 7);

        $this->assertSame('ACTIVE', $this->registry->find($moduleId)['status']);
        $this->assertSame(2, $this->testDb->table('log_audit_logs')->countAllResults());
    }

    public function testConfigurationStoresAudienceButDoesNotGrantActions(): void
    {
        $moduleId = $this->registry->create($this->input('MODULE_REGISTRY'), 7);
        $this->registry->saveConfiguration($moduleId, ['audiences' => ['SUPERADMIN', 'USER'], 'administrators' => ['7']], 7);
        $config = $this->registry->configuration($moduleId);
        $this->assertSame(['SUPERADMIN', 'USER'], $config['audiences']);
        $this->assertSame([7], $config['administrators']);
        $this->assertSame([], $config['actions']);
        $this->assertSame(0, $this->testDb->table('rbac_user_module_actions')->countAllResults());
        $this->assertSame('MODULE_CONFIGURATION_UPDATED', $this->testDb->table('log_audit_logs')->orderBy('audit_log_id', 'DESC')->get()->getRowArray()['event_code']);
    }

    public function testConfigurationStoresAndReturnsValidatedCompanyAndBranchScopes(): void
    {
        $moduleId=$this->registry->create($this->input('OUTLET_LIST'),7);
        $this->testDb->table('org_companies')->insert(['company_id'=>10,'company_code'=>'C10','company_name'=>'North Company','status'=>'ACTIVE']);
        $this->testDb->table('org_branches')->insert(['branch_id'=>20,'company_id'=>10,'branch_code'=>'B20','branch_name'=>'North Branch','status'=>'ACTIVE']);
        $this->registry->saveConfiguration($moduleId,['audiences'=>['USER'],'company_scopes'=>['10'],'branch_scopes'=>['10:20']],7);
        self::assertSame(1,$this->testDb->table('mod_module_companies')->where(['module_id'=>$moduleId,'company_id'=>10])->countAllResults());
        self::assertSame(1,$this->testDb->table('mod_module_branches')->where(['module_id'=>$moduleId,'company_id'=>10,'branch_id'=>20])->countAllResults());
        $config=$this->registry->configuration($moduleId);
        self::assertSame([10],$config['company_scopes']);
        self::assertSame(['10:20'],$config['branch_scopes']);
        $this->registry->saveConfiguration($moduleId,['audiences'=>['USER'],'module_scope_configured'=>'1'],7);
        self::assertSame(0,$this->testDb->table('mod_module_companies')->where('module_id',$moduleId)->countAllResults());
        self::assertSame(0,$this->testDb->table('mod_module_branches')->where('module_id',$moduleId)->countAllResults());
    }

    public function testBranchScopeMustBelongToSelectedCompany(): void
    {
        $moduleId=$this->registry->create($this->input('OUTLET_LIST'),7);
        $this->testDb->table('org_companies')->insertBatch([
            ['company_id'=>10,'company_code'=>'C10','company_name'=>'North Company','status'=>'ACTIVE'],
            ['company_id'=>11,'company_code'=>'C11','company_name'=>'South Company','status'=>'ACTIVE'],
        ]);
        $this->testDb->table('org_branches')->insert(['branch_id'=>21,'company_id'=>11,'branch_code'=>'B21','branch_name'=>'South Branch','status'=>'ACTIVE']);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('must belong to a selected company');
        $this->registry->saveConfiguration($moduleId,['audiences'=>['USER'],'company_scopes'=>['10'],'branch_scopes'=>['11:21']],7);
    }

    public function testInvalidAudienceIsRejected(): void
    {
        $moduleId = $this->registry->create($this->input('MODULE_REGISTRY'), 7);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('valid module audiences');
        $this->registry->saveConfiguration($moduleId, ['audiences' => ['ROOT']], 7);
    }

    public function testUnapprovedActionHandlerIsRejected(): void
    {
        $moduleId = $this->registry->create($this->input('INVENTORY'), 7);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('explicitly approved server-side handler');
        $this->registry->saveConfiguration($moduleId, ['audiences' => ['USER'], 'actions' => [[
            'action_code' => 'INVENTORY.CREATE', 'action_name' => 'Create Inventory', 'handler_key' => 'arbitrary.controller', 'approval_mode' => 'COMBINED',
        ]]], 7);
    }

    public function testCombinedApprovalModeCanBeStoredOnlyForApprovedHandler(): void
    {
        $moduleId = $this->registry->create($this->input('MODULE_REGISTRY'), 7);
        $this->registry->saveConfiguration($moduleId, ['audiences' => ['SUPERADMIN'], 'actions' => [[
            'action_code' => 'MODULE_REGISTRY.VIEW', 'action_name' => 'View Module Registry', 'handler_key' => 'MODULE_REGISTRY.INDEX', 'approval_mode' => 'COMBINED',
        ]]], 7);
        $action = $this->testDb->table('mod_module_actions')->where('module_id', $moduleId)->get()->getRowArray();
        $this->assertSame('COMBINED', $action['approval_mode']);
        $this->assertSame(0, (int) $action['is_enabled']);
        $this->assertSame('MODULE_REGISTRY.INDEX', $action['handler_key']);
    }

    public function testEmployeeManagementActivationRequiresApprovedEnabledHandlers(): void
    {
        $input=$this->input('EMPLOYEE_MANAGEMENT');
        $input['route_path']='/user-management/users';
        $moduleId=$this->registry->create($input,7);
        foreach(['VIEW','MANAGE_ACCESS','APPROVE'] as $index=>$code) {
            $this->testDb->table('mod_module_actions')->insert(['module_id'=>$moduleId,'action_code'=>$code,'action_name'=>$code,'handler_key'=>null,'is_enabled'=>0,'requires_approval'=>0,'approval_mode'=>'NONE']);
        }
        try {
            $this->registry->changeStatus($moduleId,'ACTIVE',7);
            $this->fail('Employee Management must not activate before handler review.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('enabled action handlers',$exception->getMessage());
        }
        $this->registry->saveConfiguration($moduleId,['audiences'=>['USER'],'actions'=>[
            ['action_code'=>'VIEW','action_name'=>'View User Management','handler_key'=>'USER_MANAGEMENT.INDEX','approval_mode'=>'NONE','is_enabled'=>'1'],
            ['action_code'=>'MANAGE_ACCESS','action_name'=>'Manage User Access','handler_key'=>'USER_MANAGEMENT.MANAGE_ACCESS','approval_mode'=>'COMBINED','is_enabled'=>'1'],
            ['action_code'=>'APPROVE','action_name'=>'Approve User Changes','handler_key'=>'USER_ACCESS_APPROVAL.INDEX','approval_mode'=>'NONE','is_enabled'=>'1'],
        ]],7);
        $this->registry->changeStatus($moduleId,'ACTIVE',7);
        $this->assertSame('ACTIVE',$this->registry->find($moduleId)['status']);
        $this->assertSame(0,$this->testDb->table('rbac_user_module_actions')->countAllResults());
    }

    public function testEmployeeManagementAccessActionCannotBeEnabledWithoutApproval(): void
    {
        $input=$this->input('EMPLOYEE_MANAGEMENT');
        $input['route_path']='/user-management/users';
        $moduleId=$this->registry->create($input,7);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('must use an approval workflow');
        $this->registry->saveConfiguration($moduleId,['audiences'=>['USER'],'actions'=>[
            ['action_code'=>'MANAGE_ACCESS','action_name'=>'Manage User Access','handler_key'=>'USER_MANAGEMENT.MANAGE_ACCESS','approval_mode'=>'NONE','is_enabled'=>'1'],
        ]],7);
    }

    public function testOutletListRequiresStaticRouteAndAllApprovedHandlersBeforeActivation(): void
    {
        $input=$this->input('OUTLET_LIST');
        $input['route_path']='/outlets';
        $moduleId=$this->registry->create($input,7);
        foreach(['VIEW','CREATE','UPDATE','DELETE','APPROVE'] as $code) $this->testDb->table('mod_module_actions')->insert(['module_id'=>$moduleId,'action_code'=>$code,'is_enabled'=>0,'approval_mode'=>'NONE']);
        try { $this->registry->changeStatus($moduleId,'ACTIVE',7); self::fail('Outlet List must not activate before the action handlers are ready.'); }
        catch(DomainException $exception) { self::assertStringContainsString('action handlers',$exception->getMessage()); }
        $configs=[];
        foreach([
            'VIEW'=>['OUTLET_LIST.INDEX','NONE'], 'CREATE'=>['OUTLET_LIST.CREATE','COMBINED'],
            'UPDATE'=>['OUTLET_LIST.UPDATE','COMBINED'], 'DELETE'=>['OUTLET_LIST.ARCHIVE','COMBINED'],
            'APPROVE'=>['OUTLET_LIST.APPROVE','NONE'],
        ] as $code=>[$handler,$mode]) $configs[]=['action_code'=>$code,'action_name'=>$code,'handler_key'=>$handler,'approval_mode'=>$mode,'is_enabled'=>'1'];
        $this->registry->saveConfiguration($moduleId,['audiences'=>['USER'],'actions'=>$configs],7);
        $this->registry->changeStatus($moduleId,'ACTIVE',7);
        self::assertSame('ACTIVE',$this->registry->find($moduleId)['status']);
    }

    public function testArbitraryRouteIsRejected(): void
    {
        $input = $this->input('ROUTE_TEST');
        $input['route_path'] = 'https://example.invalid/execute';
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('approved route identifier');
        $this->registry->create($input, 7);
    }

    public function testModuleCodeMustBeUnique(): void
    {
        $this->registry->create($this->input('MODULE_DUPLICATE'), 7);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already in use');
        $this->registry->create($this->input('MODULE_DUPLICATE'), 7);
    }

    /** @return array<string, mixed> */
    private function input(string $code): array
    {
        return [
            'module_code' => $code,
            'module_name' => 'Test ' . $code,
            'module_type' => 'ADMIN',
            'sort_order' => '10',
            'route_path' => $code === 'MODULE_REGISTRY' ? '/superadmin/modules' : '',
        ];
    }
}
