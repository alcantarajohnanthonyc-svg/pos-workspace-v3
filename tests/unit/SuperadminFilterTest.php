<?php

namespace Tests\Unit;

use App\Filters\SuperadminFilter;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class SuperadminFilterTest extends CIUnitTestCase
{
    private BaseConnection $fixtureDb;
    private string $originalPrefix='';

    protected function setUp(): void
    {
        parent::setUp();
        $databaseConfig=config(Database::class);
        $databaseConfig->defaultGroup='tests';
        $this->fixtureDb=Database::connect('tests');
        $this->originalPrefix=$this->fixtureDb->getPrefix();
        $this->fixtureDb->setPrefix('');
        foreach (['log_audit_logs','usr_accounts','usr_roles'] as $table) $this->fixtureDb->query('DROP TABLE IF EXISTS '.$table);
        foreach ([
            'CREATE TABLE usr_roles (role_id INTEGER PRIMARY KEY,role_code TEXT NOT NULL,role_name TEXT NOT NULL,status TEXT NOT NULL)',
            'CREATE TABLE usr_accounts (user_id INTEGER PRIMARY KEY,first_name TEXT,last_name TEXT,email TEXT NOT NULL,account_status TEXT NOT NULL,role_id INTEGER,avatar_url TEXT)',
            'CREATE TABLE log_audit_logs (audit_log_id INTEGER PRIMARY KEY AUTOINCREMENT,actor_user_id INTEGER,module_id INTEGER,event_code TEXT NOT NULL,result TEXT NOT NULL,before_data TEXT,after_data TEXT,created_at TEXT NOT NULL)',
        ] as $sql) $this->fixtureDb->query($sql);
        $this->fixtureDb->table('usr_roles')->insertBatch([
            ['role_id'=>1,'role_code'=>'SUPERADMIN','role_name'=>'Superadmin','status'=>'ACTIVE'],
            ['role_id'=>2,'role_code'=>'USER','role_name'=>'User','status'=>'ACTIVE'],
            ['role_id'=>3,'role_code'=>'SUPERADMIN','role_name'=>'Superadmin','status'=>'INACTIVE'],
        ]);
        $this->fixtureDb->table('usr_accounts')->insertBatch([
            ['user_id'=>1,'first_name'=>'Admin','last_name'=>'Fixture','email'=>'admin@example.invalid','account_status'=>'ACTIVE','role_id'=>1],
            ['user_id'=>2,'first_name'=>'User','last_name'=>'Fixture','email'=>'user@example.invalid','account_status'=>'ACTIVE','role_id'=>2],
            ['user_id'=>3,'first_name'=>'Inactive','last_name'=>'Admin','email'=>'inactive@example.invalid','account_status'=>'ACTIVE','role_id'=>3],
            ['user_id'=>4,'first_name'=>'Suspended','last_name'=>'Admin','email'=>'suspended@example.invalid','account_status'=>'SUSPENDED','role_id'=>1],
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['log_audit_logs','usr_accounts','usr_roles'] as $table) $this->fixtureDb->query('DROP TABLE IF EXISTS '.$table);
        $this->fixtureDb->setPrefix($this->originalPrefix);
        $this->fixtureDb->close();
        parent::tearDown();
    }

    public function testExplicitActiveSuperadminRoleIsAllowed(): void
    {
        session()->set(['is_authenticated'=>true,'user_id'=>1]);
        $result=(new SuperadminFilter())->before(service('request'));
        self::assertNull($result);
    }

    public function testOrdinaryUserIsDenied(): void
    {
        session()->set(['is_authenticated'=>true,'user_id'=>2]);
        $result=(new SuperadminFilter())->before(service('request'));
        self::assertSame(403,$result->getStatusCode());
        self::assertSame('Access denied.',$result->getBody());
    }

    public function testInactiveRoleAndAccountAreDenied(): void
    {
        session()->set(['is_authenticated'=>true,'user_id'=>3]);
        $inactiveRole=(new SuperadminFilter())->before(service('request'));
        self::assertSame(403,$inactiveRole->getStatusCode());
        session()->set(['is_authenticated'=>true,'user_id'=>4]);
        $inactiveAccount=(new SuperadminFilter())->before(service('request'));
        self::assertSame(403,$inactiveAccount->getStatusCode());
    }

    public function testMissingAuthenticationIsRedirectedToLogin(): void
    {
        session()->destroy();
        $result=(new SuperadminFilter())->before(service('request'));
        self::assertSame(302,$result->getStatusCode());
        self::assertStringContainsString('/login',$result->getHeaderLine('Location'));
    }
}
