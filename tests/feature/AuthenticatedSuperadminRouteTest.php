<?php

namespace Tests\Feature;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class AuthenticatedSuperadminRouteTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $fixtureDb;
    private string $tablePrefix='';
    private ?string $oldAllowedDomain=null;
    private ?string $oldServerAllowedDomain=null;
    private ?string $oldEnvAllowedDomain=null;

    protected function setUp(): void
    {
        parent::setUp();
        config(Database::class)->defaultGroup='tests';
        $this->fixtureDb=Database::connect('tests');
        $this->tablePrefix=$this->fixtureDb->getPrefix();
        $tables=['log_audit_logs','sec_active_sessions','rbac_user_module_actions','rbac_role_module_actions','mod_module_administrators','mod_module_audiences','mod_module_actions','mod_modules','usr_accounts','usr_roles','org_branches','org_companies'];
        foreach ($tables as $table) $this->fixtureDb->query('DROP TABLE IF EXISTS '.$this->tablePrefix.$table);
        foreach ([
            'CREATE TABLE usr_roles (role_id INTEGER PRIMARY KEY,role_code TEXT NOT NULL,role_name TEXT NOT NULL,status TEXT NOT NULL)',
            'CREATE TABLE usr_accounts (user_id INTEGER PRIMARY KEY,username TEXT,employee_code TEXT,first_name TEXT,last_name TEXT,avatar_url TEXT,birthday TEXT,email TEXT NOT NULL,password_hash TEXT,role_id INTEGER,position_id INTEGER,default_module_id INTEGER,account_status TEXT NOT NULL,created_at TEXT,updated_at TEXT)',
            'CREATE TABLE mod_modules (module_id INTEGER PRIMARY KEY,module_code TEXT NOT NULL,module_name TEXT NOT NULL,short_name TEXT,module_type TEXT,description TEXT,route_path TEXT,icon TEXT,sort_order INTEGER,status TEXT NOT NULL,parent_module_id INTEGER,created_by INTEGER,created_at TEXT,updated_at TEXT)',
            'CREATE TABLE mod_module_actions (module_action_id INTEGER PRIMARY KEY,module_id INTEGER NOT NULL,action_code TEXT NOT NULL,action_name TEXT,action_description TEXT,handler_key TEXT,is_enabled INTEGER NOT NULL,requires_approval INTEGER NOT NULL,approval_mode TEXT NOT NULL,requires_company_scope INTEGER NOT NULL DEFAULT 0,requires_branch_scope INTEGER NOT NULL DEFAULT 0,created_at TEXT,updated_at TEXT)',
            'CREATE TABLE rbac_role_module_actions (role_id INTEGER NOT NULL,module_action_id INTEGER NOT NULL)',
            'CREATE TABLE rbac_user_module_actions (user_id INTEGER NOT NULL,module_action_id INTEGER NOT NULL)',
            'CREATE TABLE mod_module_audiences (module_id INTEGER NOT NULL,audience_code TEXT NOT NULL)',
            'CREATE TABLE mod_module_administrators (module_id INTEGER NOT NULL,user_id INTEGER NOT NULL,assigned_by INTEGER)',
            'CREATE TABLE org_companies (company_id INTEGER PRIMARY KEY,company_code TEXT NOT NULL,company_name TEXT NOT NULL,status TEXT NOT NULL)',
            'CREATE TABLE org_branches (branch_id INTEGER PRIMARY KEY,company_id INTEGER NOT NULL,branch_code TEXT NOT NULL,branch_name TEXT NOT NULL,status TEXT NOT NULL)',
            'CREATE TABLE sec_active_sessions (user_id INTEGER PRIMARY KEY,session_hash TEXT NOT NULL,expires_at TEXT NOT NULL,last_seen_at TEXT NOT NULL)',
            'CREATE TABLE log_audit_logs (audit_log_id INTEGER PRIMARY KEY AUTOINCREMENT,actor_user_id INTEGER,module_id INTEGER,event_code TEXT NOT NULL,result TEXT NOT NULL,before_data TEXT,after_data TEXT,created_at TEXT NOT NULL)',
        ] as $sql) $this->fixtureDb->query(str_replace('CREATE TABLE ','CREATE TABLE '.$this->tablePrefix,$sql));
        $this->fixtureDb->table('usr_roles')->insertBatch([
            ['role_id'=>1,'role_code'=>'SUPERADMIN','role_name'=>'Superadmin','status'=>'ACTIVE'],
            ['role_id'=>2,'role_code'=>'USER','role_name'=>'User','status'=>'ACTIVE'],
        ]);
        $this->fixtureDb->table('usr_accounts')->insertBatch([
            ['user_id'=>1,'first_name'=>'Admin','last_name'=>'Fixture','email'=>'admin@example.invalid','role_id'=>1,'account_status'=>'ACTIVE'],
            ['user_id'=>2,'first_name'=>'User','last_name'=>'Fixture','email'=>'user@example.invalid','role_id'=>2,'account_status'=>'ACTIVE'],
        ]);
        $this->fixtureDb->table('mod_modules')->insert(['module_id'=>1,'module_code'=>'EMPLOYEE_MANAGEMENT','module_name'=>'Employee Management','status'=>'ACTIVE','sort_order'=>1]);
        $this->fixtureDb->table('mod_module_actions')->insert(['module_action_id'=>1,'module_id'=>1,'action_code'=>'VIEW','action_name'=>'View','is_enabled'=>1,'requires_approval'=>0,'approval_mode'=>'NONE']);
        $this->fixtureDb->table('rbac_role_module_actions')->insert(['role_id'=>2,'module_action_id'=>1]);
        $this->fixtureDb->table('rbac_user_module_actions')->insert(['user_id'=>2,'module_action_id'=>1]);

        $this->oldAllowedDomain=$_ENV['google.allowedDomains']??null;
        $this->oldServerAllowedDomain=$_SERVER['google.allowedDomains']??null;
        $this->oldEnvAllowedDomain=getenv('google.allowedDomains')===false?null:(string)getenv('google.allowedDomains');
        $_ENV['google.allowedDomains']='example.invalid';
        $_SERVER['google.allowedDomains']='example.invalid';
        putenv('google.allowedDomains=example.invalid');
        $sessionId='abcdef0123456789abcdef0123456789';
        $expiry=date('Y-m-d H:i:s',time()+3600);
        foreach ([1,2] as $userId) $this->fixtureDb->table('sec_active_sessions')->insert(['user_id'=>$userId,'session_hash'=>hash('sha256',$sessionId),'expires_at'=>$expiry,'last_seen_at'=>date('Y-m-d H:i:s')]);
    }

    protected function tearDown(): void
    {
        foreach (['log_audit_logs','sec_active_sessions','rbac_user_module_actions','rbac_role_module_actions','mod_module_administrators','mod_module_audiences','mod_module_actions','mod_modules','usr_accounts','usr_roles','org_branches','org_companies'] as $table) $this->fixtureDb->query('DROP TABLE IF EXISTS '.$this->tablePrefix.$table);
        if ($this->oldAllowedDomain===null) unset($_ENV['google.allowedDomains']); else $_ENV['google.allowedDomains']=$this->oldAllowedDomain;
        if ($this->oldServerAllowedDomain===null) unset($_SERVER['google.allowedDomains']); else $_SERVER['google.allowedDomains']=$this->oldServerAllowedDomain;
        if ($this->oldEnvAllowedDomain===null) putenv('google.allowedDomains'); else putenv('google.allowedDomains='.$this->oldEnvAllowedDomain);
        $this->fixtureDb->close();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuthenticatedSuperadminCanReachTheSharedLandingPage(): void
    {
        session_id('abcdef0123456789abcdef0123456789');
        $result = $this->withSession(['user_id'=>1,'is_authenticated'=>true,'role_code'=>'SUPERADMIN'])->get('/dashboard');

        $result->assertStatus(200);
        $result->assertSee('Your workspace is ready');
        $result->assertDontSee('Module Registry');
        $result->assertDontSee('Approval Queue');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAuthenticatedUserCanReachTheSharedLandingPage(): void
    {
        session_id('abcdef0123456789abcdef0123456789');
        $result = $this->withSession(['user_id'=>2,'is_authenticated'=>true,'role_code'=>'USER'])->get('/dashboard');

        $result->assertStatus(200);
        $result->assertSee('Your workspace is ready');
        $result->assertDontSee('Outlet List');
        $result->assertDontSee('User Management');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRevokedRegisteredSessionIsRejectedOnNextProtectedRequest(): void
    {
        $this->fixtureDb->table('sec_active_sessions')->where('user_id',1)->delete();
        session_id('abcdef0123456789abcdef0123456789');

        $result=$this->withSession(['user_id'=>1,'is_authenticated'=>true,'role_code'=>'SUPERADMIN'])->get('/dashboard');

        $result->assertRedirectTo('/login');
        $result->assertSessionHas('error','Your workspace session has expired or was replaced. Please sign in again.');
    }
}
